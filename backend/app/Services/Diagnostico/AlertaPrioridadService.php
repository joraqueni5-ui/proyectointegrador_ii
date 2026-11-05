<?php

namespace App\Services\Diagnostico;

use App\Models\DiagnosticoCNN;

class AlertaPrioridadService
{
    /**
     * Calcula la prioridad de una alerta fitosanitaria.
     *
     * Máximo:
     * - Riesgo de enfermedad: 35 puntos
     * - Severidad observada: 25 puntos
     * - Plantas afectadas: 20 puntos
     * - Reincidencia: 15 puntos
     * - Validación agronómica: 5 puntos
     *
     * TOTAL: 100 puntos
     */
    public function calcular(DiagnosticoCNN $diagnostico): array
    {
        $diagnostico->loadMissing([
            'enfermedad',
            'inspeccion',
        ]);

        $motivos = [];
        $puntaje = 0;

        /*
        |--------------------------------------------------------------------------
        | 1. RIESGO BASE DE LA ENFERMEDAD - máximo 35 puntos
        |--------------------------------------------------------------------------
        */
        $riesgo = $this->normalizarRiesgo(
            $diagnostico->enfermedad?->nivel_riesgo_base
        );

        $puntosRiesgo = match ($riesgo) {
            'Critico' => 35,
            'Alto' => 25,
            'Medio' => 10,
            'Bajo' => 0,
            default => 0,
        };

        $puntaje += $puntosRiesgo;

        $motivos[] = [
            'factor' => 'Riesgo de la enfermedad',
            'valor' => $riesgo ?? 'No registrado',
            'puntos' => $puntosRiesgo,
            'maximo' => 35,
        ];

        /*
        |--------------------------------------------------------------------------
        | 2. SEVERIDAD AGRONÓMICA - máximo 25 puntos
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        | Esto NO utiliza la confianza de la CNN.
        | La severidad corresponde a la observación/validación agronómica.
        |
        */
        $severidad = $diagnostico->nivel_severidad;

        $puntosSeveridad = match ($severidad) {
            'Muy_severa' => 25,
            'Severa' => 20,
            'Moderada' => 12,
            'Leve' => 5,
            'Sin_afeccion' => 0,
            default => 0,
        };

        $puntaje += $puntosSeveridad;

        $motivos[] = [
            'factor' => 'Severidad observada',
            'valor' => $severidad
                ? str_replace('_', ' ', $severidad)
                : 'No registrada',
            'puntos' => $puntosSeveridad,
            'maximo' => 25,
        ];

        /*
        |--------------------------------------------------------------------------
        | 3. PORCENTAJE DE PLANTAS AFECTADAS - máximo 20 puntos
        |--------------------------------------------------------------------------
        */
        $porcentajeAfectado = $diagnostico->inspeccion?->pct_plantas_afectadas;

        $puntosAfectacion = 0;

        if ($porcentajeAfectado !== null) {
            $porcentajeAfectado = (float) $porcentajeAfectado;

            $puntosAfectacion = match (true) {
                $porcentajeAfectado <= 0 => 0,
                $porcentajeAfectado <= 10 => 5,
                $porcentajeAfectado <= 25 => 10,
                $porcentajeAfectado <= 50 => 15,
                default => 20,
            };
        }

        $puntaje += $puntosAfectacion;

        $motivos[] = [
            'factor' => 'Plantas afectadas',
            'valor' => $porcentajeAfectado !== null
                ? round($porcentajeAfectado, 2).'%'
                : 'No registrado',
            'puntos' => $puntosAfectacion,
            'maximo' => 20,
        ];

        /*
        |--------------------------------------------------------------------------
        | 4. REINCIDENCIA EN LA PARCELA - máximo 15 puntos
        |--------------------------------------------------------------------------
        |
        | Busca inspecciones anteriores de los últimos 30 días donde haya
        | aparecido la misma enfermedad.
        |
        | Contamos INSPECCIONES y no fotografías.
        |
        */
        $reincidencias = $this->contarReincidencias($diagnostico);

        $puntosReincidencia = match (true) {
            $reincidencias >= 3 => 15,
            $reincidencias === 2 => 10,
            $reincidencias === 1 => 5,
            default => 0,
        };

        $puntaje += $puntosReincidencia;

        $motivos[] = [
            'factor' => 'Reincidencia en la parcela',
            'valor' => $reincidencias.' inspección(es) previa(s) en 30 días',
            'puntos' => $puntosReincidencia,
            'maximo' => 15,
        ];

        /*
        |--------------------------------------------------------------------------
        | 5. VALIDACIÓN AGRONÓMICA - máximo 5 puntos
        |--------------------------------------------------------------------------
        */
        $estadoValidacion = $diagnostico->estado_validacion;

        $puntosValidacion = in_array(
            $estadoValidacion,
            ['Confirmado', 'Corregido'],
            true
        ) ? 5 : 0;

        $puntaje += $puntosValidacion;

        $motivos[] = [
            'factor' => 'Validación agronómica',
            'valor' => $estadoValidacion ?? 'Pendiente',
            'puntos' => $puntosValidacion,
            'maximo' => 5,
        ];

        /*
        |--------------------------------------------------------------------------
        | Resultado final
        |--------------------------------------------------------------------------
        */
        $puntaje = min(100, max(0, $puntaje));

        $nivelPorPuntaje = $this->nivelSegunPuntaje($puntaje);

        $nivelFinal = $this->aplicarPrioridadMinimaPorRiesgo(
            $nivelPorPuntaje,
            $riesgo
        );

        if ($nivelFinal !== $nivelPorPuntaje) {
            $motivos[] = [
                'factor' => 'Regla de prioridad mínima',
                'valor' => "El riesgo base {$riesgo} impide reducir la alerta a {$nivelPorPuntaje}",
                'puntos' => 0,
                'maximo' => 0,
            ];
        }

        return [
            'puntaje' => $puntaje,
            'nivel' => $nivelFinal,
            'nivel_por_puntaje' => $nivelPorPuntaje,
            'motivos' => $motivos,
            'prioridad_automatica' => true,
        ];
    }

    private function aplicarPrioridadMinimaPorRiesgo(
        string $nivelCalculado,
        ?string $riesgo
    ): string {
        $peso = [
            'Bajo' => 1,
            'Medio' => 2,
            'Alto' => 3,
            'Critico' => 4,
        ];

        $minimo = match ($riesgo) {
            'Critico' => 'Critico',
            'Alto' => 'Alto',
            default => 'Bajo',
        };

        return ($peso[$nivelCalculado] ?? 1) >= ($peso[$minimo] ?? 1)
            ? $nivelCalculado
            : $minimo;
    }

    /**
     * Cuenta inspecciones anteriores con la misma enfermedad
     * en la misma parcela durante los últimos 30 días.
     */
    private function contarReincidencias(DiagnosticoCNN $diagnostico): int
    {
        $inspeccion = $diagnostico->inspeccion;

        if (
            ! $inspeccion ||
            ! $inspeccion->id_parcela ||
            ! $diagnostico->id_enfermedad
        ) {
            return 0;
        }

        $fechaReferencia = $inspeccion->fecha_visita
            ? $inspeccion->fecha_visita->copy()
            : now();

        $desde = $fechaReferencia
            ->copy()
            ->subDays(30)
            ->toDateString();

        $hasta = $fechaReferencia->toDateString();

        return DiagnosticoCNN::query()
            ->where('id_enfermedad', $diagnostico->id_enfermedad)

            // No contar el diagnóstico actual.
            ->where('id', '<>', $diagnostico->id)

            // Tampoco contar otras fotografías de la misma inspección.
            ->where('id_inspeccion', '<>', $inspeccion->id)

            ->whereIn('estado_validacion', [
                'Confirmado',
                'Corregido',
            ])

            ->whereHas('inspeccion', function ($query) use (
                $inspeccion,
                $desde,
                $hasta
            ) {
                $query
                    ->where('id_parcela', $inspeccion->id_parcela)
                    ->whereBetween('fecha_visita', [$desde, $hasta]);
            })

            // Una inspección cuenta una sola vez aunque después
            // implementemos varias fotografías.
            ->distinct()
            ->count('id_inspeccion');
    }

    /**
     * Convierte el puntaje 0-100 a un nivel de prioridad.
     */
    private function nivelSegunPuntaje(int $puntaje): string
    {
        return match (true) {
            $puntaje >= 75 => 'Critico',
            $puntaje >= 50 => 'Alto',
            $puntaje >= 30 => 'Medio',
            default => 'Bajo',
        };
    }

    /**
     * Evita problemas entre "Critico" y "Crítico".
     */
    private function normalizarRiesgo(?string $riesgo): ?string
    {
        if ($riesgo === null) {
            return null;
        }

        return match ($riesgo) {
            'Crítico', 'Critico' => 'Critico',
            'Alto' => 'Alto',
            'Medio' => 'Medio',
            'Bajo' => 'Bajo',
            default => $riesgo,
        };
    }
}
