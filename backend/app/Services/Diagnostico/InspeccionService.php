<?php

namespace App\Services\Diagnostico;

use App\DTOs\Diagnostico\InspeccionDTO;
use App\Exceptions\ReglaNegocioException;
use App\Models\Inspeccion;
use Illuminate\Pagination\LengthAwarePaginator;

class InspeccionService
{
    private array $relations = [
        'usuario:id,name,paterno',
        'programadoPor:id,name,paterno',
        'validadoPor:id,name,paterno',
        'parcela:id,codigo_parcela,nombre_referencial,id_productor',
        'parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad',
        'cultivo:id,variedad_papa,id_parcela,fecha_siembra,estado',
        'etapaFenologica:id,nombre,codigo',
    ];

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return Inspeccion::with($this->relations)

            ->when($filtros['id_cultivo'] ?? null, function ($query, $idCultivo) {
                $query->where('id_cultivo', $idCultivo);
            })
            ->when($filtros['tipo_inspeccion'] ?? null, function ($query, $tipoInspeccion) {
                $query->where('tipo_inspeccion', $tipoInspeccion);
            })
            ->when($filtros['id_parcela'] ?? null, function ($query, $idParcela) {
                $query->where('id_parcela', $idParcela);
            })
            ->when($filtros['id_usuario'] ?? null, function ($query, $idUsuario) {
                $query->where('id_usuario', $idUsuario);
            })
            ->when($filtros['estado'] ?? null, function ($query, $estado) {
                $query->where('estado', $estado);
            })
            ->when($filtros['fecha_desde'] ?? null, function ($query, $desde) {
                $query->whereDate('fecha_visita', '>=', $desde);
            })
            ->when($filtros['fecha_hasta'] ?? null, function ($query, $hasta) {
                $query->whereDate('fecha_visita', '<=', $hasta);
            })
            ->when($filtros['atrasadas'] ?? false, function ($query) {
                $query->where('estado', 'Pendiente')->whereDate('fecha_visita', '<', today());
            })
            ->when($filtros['diagnostico_elegible'] ?? false, function ($query) {
                $query
                    ->where('tipo_inspeccion', 'Cultivo')
                    ->whereIn('estado', ['En_proceso', 'Completada'])
                    ->whereNotNull('id_cultivo')
                    ->whereHas('cultivo', fn ($cultivo) => $cultivo->where('estado', 'Activo'))
                    ->whereDoesntHave('diagnosticos', fn ($diagnostico) => $diagnostico->where('estado_validacion', 'Pendiente')
                    );
            })
            ->orderBy('fecha_visita', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Conteo real por estado (no solo de la página visible) y visitas atrasadas.
     */
    public function resumen(): array
    {
        $porEstado = Inspeccion::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $estados = ['Pendiente', 'En_proceso', 'Completada', 'Validada', 'No_realizada'];

        return [
            'total' => (int) $porEstado->sum(),
            'por_estado' => collect($estados)->mapWithKeys(fn ($e) => [$e => (int) ($porEstado[$e] ?? 0)])->all(),
            'atrasadas' => Inspeccion::where('estado', 'Pendiente')->whereDate('fecha_visita', '<', today())->count(),
        ];
    }

    public function verConDiagnosticos(Inspeccion $inspeccion): Inspeccion
    {
        return $inspeccion->load([
            ...$this->relations,
            'diagnosticos.enfermedad:id,nombre_comun,nombre_cientifico',
        ]);
    }

    public function crear(InspeccionDTO $dto): Inspeccion
    {
        return Inspeccion::create($dto->toArray())
            ->load($this->relations);
    }

    public function actualizar(Inspeccion $inspeccion, InspeccionDTO $dto): Inspeccion
    {
        if ($inspeccion->estado === 'Validada') {
            throw new ReglaNegocioException('Una inspección validada no se puede modificar.');
        }

        // El estado solo cambia desde actualizarEstado(), que aplica las reglas del flujo.
        $inspeccion->update([...$dto->toArray(), 'estado' => $inspeccion->estado]);

        return $inspeccion->fresh()
            ->load($this->relations);
    }

    public function actualizarEstado(Inspeccion $inspeccion, array $data, int $userId): Inspeccion
    {
        $estado = $data['estado'];

        $this->validarTransicion($inspeccion, $estado);

        $campos = [
            'estado' => $estado,
        ];

        if ($estado === 'En_proceso' && ! $inspeccion->fecha_inicio) {
            $campos['fecha_inicio'] = now();
        }

        if ($estado === 'Completada' && ! $inspeccion->fecha_finalizacion) {
            $campos['fecha_finalizacion'] = now();
        }

        if ($estado === 'Validada') {
            $campos['id_validado_por'] = $userId;
            $campos['fecha_validacion'] = now();
        }

        if ($estado === 'No_realizada') {
            $campos['motivo_no_realizada'] = $data['motivo_no_realizada'] ?? null;
            $campos['fecha_reprogramada'] = $data['fecha_reprogramada'] ?? null;
            $campos['recomendacion_reprogramacion'] = $data['recomendacion_reprogramacion'] ?? null;
        }

        $inspeccion->update($campos);

        return $inspeccion->fresh()
            ->load($this->relations);
    }

    private function validarTransicion(Inspeccion $inspeccion, string $nuevoEstado): void
    {
        if ($inspeccion->estado === $nuevoEstado && $nuevoEstado !== 'No_realizada') {
            return;
        }

        if ($inspeccion->estado === 'Validada') {
            throw new ReglaNegocioException('Una inspección validada no puede cambiar de estado.');
        }

        if (in_array($nuevoEstado, ['Pendiente', 'No_realizada'], true) && $inspeccion->diagnosticos()->exists()) {
            throw new ReglaNegocioException('La inspección ya tiene diagnósticos registrados; no puede volver a pendiente ni marcarse como no realizada.');
        }

        if ($nuevoEstado === 'Validada') {
            if ($inspeccion->estado !== 'Completada') {
                throw new ReglaNegocioException('Solo se puede validar una inspección completada.');
            }

            if ($inspeccion->diagnosticos()->where('estado_validacion', 'Pendiente')->exists()) {
                throw new ReglaNegocioException('La inspección tiene diagnósticos pendientes de validación.');
            }
        }
    }
}
