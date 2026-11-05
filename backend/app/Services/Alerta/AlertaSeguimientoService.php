<?php

namespace App\Services\Alerta;

use App\Models\Alerta;
use Illuminate\Validation\ValidationException;

class AlertaSeguimientoService
{
    /*
    |--------------------------------------------------------------------------
    | Transiciones permitidas del seguimiento fitosanitario
    |--------------------------------------------------------------------------
    */
    private const TRANSICIONES = [
        'Nueva' => ['En_revision', 'Descartada'],
        'En_revision' => ['Planificada', 'Escalada', 'Descartada'],
        'Planificada' => ['En_tratamiento', 'Escalada'],
        'En_tratamiento' => ['En_seguimiento', 'Escalada', 'Reincidente'],
        'En_seguimiento' => ['Resuelta', 'Escalada', 'Reincidente'],
        'Escalada' => ['En_revision', 'Planificada', 'En_tratamiento', 'En_seguimiento'],
        'Reincidente' => ['En_revision', 'Planificada', 'En_tratamiento'],
        'Resuelta' => [],
        'Descartada' => [],
    ];

    private const ESTADOS_CERRADOS = ['Resuelta', 'Descartada'];

    // Campos que el flujo puede actualizar a partir de los datos recibidos.
    private const CAMPOS_EDITABLES = [
        'id_responsable',
        'plan_manejo',
        'proxima_revision',
        'resultado_seguimiento',
        'accion_tomada',
    ];

    public function cambiarEstado(Alerta $alerta, string $nuevoEstado, array $datos = []): Alerta
    {
        $estadoActual = $alerta->estado_seguimiento ?? 'Nueva';

        $this->validarTransicion($estadoActual, $nuevoEstado);

        match ($nuevoEstado) {
            'Planificada' => $this->validarPlanificacion($datos),
            'En_tratamiento' => $this->validarInicioTratamiento($alerta, $datos),
            'En_seguimiento' => $this->validarSeguimiento($alerta, $datos),
            'Resuelta' => $this->validarResolucion($alerta, $datos),
            default => null,
        };

        $update = array_intersect_key($datos, array_flip(self::CAMPOS_EDITABLES));
        $update['estado_seguimiento'] = $nuevoEstado;

        if (array_key_exists('progreso_seguimiento', $datos)) {
            $update['progreso_seguimiento'] = min(100, max(0, (int) $datos['progreso_seguimiento']));
        }

        if ($nuevoEstado === 'En_tratamiento' && ! $alerta->fecha_inicio_tratamiento) {
            $update['fecha_inicio_tratamiento'] = now();
        }

        if ($nuevoEstado === 'En_seguimiento') {
            $update['ultima_revision_at'] = now();
        }

        if ($nuevoEstado === 'Resuelta') {
            $update['fecha_resolucion'] = now();
            $update['progreso_seguimiento'] = 100;
        }

        if (in_array($nuevoEstado, self::ESTADOS_CERRADOS, true)) {
            $update['estado'] = 'Atendida';

            if (array_key_exists('observacion_cierre', $datos)) {
                $update['observacion_cierre'] = $datos['observacion_cierre'];
            }
        } else {
            $update['estado'] = 'Activa';
        }

        $alerta->update($update);

        return $alerta->fresh([
            'diagnostico.enfermedad',
            'diagnostico.inspeccion.parcela',
            'usuarioDestino',
            'responsable',
        ]);
    }

    public function estadosDisponibles(Alerta $alerta): array
    {
        return self::TRANSICIONES[$alerta->estado_seguimiento ?? 'Nueva'] ?? [];
    }

    private function validarTransicion(string $actual, string $nuevo): void
    {
        // Repetir el estado sirve para actualizar un seguimiento abierto,
        // pero una alerta cerrada no se vuelve a cerrar.
        if ($actual === $nuevo && ! in_array($actual, self::ESTADOS_CERRADOS, true)) {
            return;
        }

        if (! in_array($nuevo, self::TRANSICIONES[$actual] ?? [], true)) {
            throw ValidationException::withMessages([
                'estado_seguimiento' => "No se puede cambiar una alerta de {$actual} a {$nuevo}.",
            ]);
        }
    }

    private function validarPlanificacion(array $datos): void
    {
        $this->exigir([
            'id_responsable' => [$datos['id_responsable'] ?? null, 'Debe asignar un responsable.'],
            'plan_manejo' => [$datos['plan_manejo'] ?? null, 'Debe registrar un plan de manejo.'],
            'proxima_revision' => [$datos['proxima_revision'] ?? null, 'Debe programar la próxima revisión.'],
        ]);
    }

    private function validarInicioTratamiento(Alerta $alerta, array $datos): void
    {
        $this->exigir([
            'id_responsable' => [$datos['id_responsable'] ?? $alerta->id_responsable, 'La alerta debe tener un responsable.'],
            'plan_manejo' => [$datos['plan_manejo'] ?? $alerta->plan_manejo, 'La alerta debe tener un plan de manejo.'],
            'proxima_revision' => [$datos['proxima_revision'] ?? $alerta->proxima_revision, 'Debe existir una próxima revisión.'],
        ]);
    }

    private function validarSeguimiento(Alerta $alerta, array $datos): void
    {
        $this->exigir([
            'accion_tomada' => [
                $datos['accion_tomada'] ?? $alerta->accion_tomada,
                'Debe registrar la acción o tratamiento realizado antes de iniciar el seguimiento.',
            ],
        ]);
    }

    private function validarResolucion(Alerta $alerta, array $datos): void
    {
        $this->exigir([
            'id_responsable' => [$datos['id_responsable'] ?? $alerta->id_responsable, 'Debe existir un responsable del seguimiento.'],
            'accion_tomada' => [$datos['accion_tomada'] ?? $alerta->accion_tomada, 'Debe registrar la acción o tratamiento realizado.'],
            'resultado_seguimiento' => [$datos['resultado_seguimiento'] ?? $alerta->resultado_seguimiento, 'Debe registrar el resultado del seguimiento.'],
            'observacion_cierre' => [$datos['observacion_cierre'] ?? $alerta->observacion_cierre, 'Debe registrar una observación final antes de resolver la alerta.'],
        ]);
    }

    /**
     * @param  array<string, array{0: mixed, 1: string}>  $campos  campo => [valor, mensaje si falta]
     */
    private function exigir(array $campos): void
    {
        $errores = [];

        foreach ($campos as $campo => [$valor, $mensaje]) {
            if ($valor === null || trim((string) $valor) === '') {
                $errores[$campo] = $mensaje;
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }
}
