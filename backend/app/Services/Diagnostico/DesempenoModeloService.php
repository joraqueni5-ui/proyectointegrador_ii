<?php

namespace App\Services\Diagnostico;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Desempeño de la CNN en producción (fase de despliegue de CRISP-DM).
 *
 * Se calcula solo con diagnósticos ya validados por un agrónomo:
 *  - "Confirmado": la IA acertó (la enfermedad final es la que predijo).
 *  - "Corregido":  la IA se equivocó (la enfermedad final es la corregida).
 *  - "Rechazado":  el diagnóstico no fue válido (no entra en la matriz de confusión).
 *  - "Pendiente":  aún sin validar.
 *
 * La clase predicha es la primera del top 3 guardado al crear el diagnóstico (no cambia al
 * corregirlo). Los diagnósticos manuales (sin top 3) no se evalúan: no los produjo la CNN.
 * Es solo lectura.
 */
class DesempenoModeloService
{
    /** Con menos diagnósticos validados que esto, las cifras son orientativas. */
    private const MUESTRA_MINIMA = 30;

    /**
     * @param  array{fecha_desde?: ?string, fecha_hasta?: ?string, modelo_version?: ?string}  $filtros
     */
    public function calcular(array $filtros = []): array
    {
        $filas = $this->consultar($filtros);

        $cnn = [];
        $manuales = 0;

        foreach ($filas as $fila) {
            $top3 = $fila->top3_predicciones ? json_decode($fila->top3_predicciones, true) : null;

            if (! is_array($top3) || $top3 === []) {
                $manuales++;

                continue;
            }

            $cnn[] = [
                'estado' => $fila->estado_validacion,
                'confianza' => (float) $fila->porcentaje_confianza,
                'mes' => substr((string) $fila->created_at, 0, 7),
                'pred_codigo' => $top3[0]['codigo_enfermedad'] ?? null,
                'pred_nombre' => $top3[0]['nombre'] ?? 'Sin clase',
                'real_codigo' => $fila->codigo_final,
                'real_nombre' => $fila->nombre_final,
            ];
        }

        $resumen = $this->resumen($cnn, $manuales);
        $utiles = array_values(array_filter($cnn, fn ($f) => in_array($f['estado'], ['Confirmado', 'Corregido'], true)));

        [$matriz, $porClase, $globales] = $this->matrizYMetricas($utiles);

        $advertencias = [];

        if (count($utiles) < self::MUESTRA_MINIMA) {
            $advertencias[] = 'Hay pocos diagnósticos validados ('.count($utiles).'): las métricas son orientativas hasta reunir al menos '.self::MUESTRA_MINIMA.'.';
        }

        if ($manuales > 0) {
            $advertencias[] = "Se excluyeron {$manuales} diagnóstico(s) registrados manualmente, que no produjo la CNN.";
        }

        return [
            'filtros' => [
                'fecha_desde' => $filtros['fecha_desde'] ?? null,
                'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
                'modelo_version' => $filtros['modelo_version'] ?? null,
            ],
            'modelos_disponibles' => $this->modelosDisponibles(),
            'resumen' => $resumen,
            'metricas_globales' => $globales,
            'matriz' => $matriz,
            'por_clase' => $porClase,
            'confianza' => $this->confianza($cnn),
            'evolucion_mensual' => $this->evolucionMensual($cnn),
            'advertencias' => $advertencias,
            'generado_en' => now()->toIso8601String(),
        ];
    }

    private function consultar(array $filtros)
    {
        return DB::table('diagnosticos_cnn as d')
            ->leftJoin('enfermedades as e', 'e.id', '=', 'd.id_enfermedad')
            ->select([
                'd.id', 'd.estado_validacion', 'd.porcentaje_confianza', 'd.top3_predicciones',
                'd.modelo_version', 'd.created_at',
                'e.codigo_enfermedad as codigo_final', 'e.nombre_comun as nombre_final',
            ])
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $f) => $q->where('d.created_at', '>=', Carbon::parse($f)->startOfDay()))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $f) => $q->where('d.created_at', '<=', Carbon::parse($f)->endOfDay()))
            ->when($filtros['modelo_version'] ?? null, fn ($q, $v) => $q->where('d.modelo_version', $v))
            ->get();
    }

    /** Versiones de modelo que han producido diagnósticos (para el filtro). */
    private function modelosDisponibles(): array
    {
        return DB::table('diagnosticos_cnn')
            ->whereNotNull('modelo_version')
            ->distinct()
            ->orderBy('modelo_version')
            ->pluck('modelo_version')
            ->all();
    }

    private function resumen(array $cnn, int $manuales): array
    {
        $conteo = ['Pendiente' => 0, 'Confirmado' => 0, 'Corregido' => 0, 'Rechazado' => 0];

        foreach ($cnn as $f) {
            if (isset($conteo[$f['estado']])) {
                $conteo[$f['estado']]++;
            }
        }

        $total = count($cnn);
        $pct = fn (int $n) => $total > 0 ? round($n / $total * 100, 1) : null;

        return [
            'total_cnn' => $total,
            'pendientes' => $conteo['Pendiente'],
            'confirmados' => $conteo['Confirmado'],
            'corregidos' => $conteo['Corregido'],
            'rechazados' => $conteo['Rechazado'],
            'validados_utiles' => $conteo['Confirmado'] + $conteo['Corregido'],
            'pct_pendientes' => $pct($conteo['Pendiente']),
            'pct_confirmados' => $pct($conteo['Confirmado']),
            'pct_corregidos' => $pct($conteo['Corregido']),
            'pct_rechazados' => $pct($conteo['Rechazado']),
            'diagnosticos_manuales_excluidos' => $manuales,
        ];
    }

    /**
     * Matriz de confusión (filas = enfermedad final, columnas = predicha por la CNN) y métricas
     * por clase y globales.
     */
    private function matrizYMetricas(array $utiles): array
    {
        $nombres = [];
        $celdas = [];

        foreach ($utiles as $f) {
            // En un diagnóstico confirmado, la enfermedad final es la que predijo la CNN.
            $real = $f['estado'] === 'Confirmado' ? $f['pred_codigo'] : $f['real_codigo'];
            $realNombre = $f['estado'] === 'Confirmado' ? $f['pred_nombre'] : ($f['real_nombre'] ?? 'Sin clase');
            $pred = $f['pred_codigo'] ?? 'SIN_CLASE';
            $real ??= 'SIN_CLASE';

            $nombres[$pred] = $f['pred_nombre'];
            $nombres[$real] = $realNombre;
            $celdas[$real][$pred] = ($celdas[$real][$pred] ?? 0) + 1;
        }

        $codigos = array_keys($nombres);
        sort($codigos);

        $valores = [];
        foreach ($codigos as $fila) {
            $valores[] = array_map(fn ($col) => $celdas[$fila][$col] ?? 0, $codigos);
        }

        $total = count($utiles);
        $aciertos = 0;
        $porClase = [];

        foreach ($codigos as $i => $codigo) {
            $tp = $valores[$i][$i];
            $aciertos += $tp;
            $soporte = array_sum($valores[$i]);
            $predichos = array_sum(array_column($valores, $i));
            $precision = $predichos > 0 ? $tp / $predichos : null;
            $recall = $soporte > 0 ? $tp / $soporte : null;
            $f1 = ($precision !== null && $recall !== null && ($precision + $recall) > 0)
                ? 2 * $precision * $recall / ($precision + $recall)
                : (($precision !== null || $recall !== null) ? 0.0 : null);

            $porClase[] = [
                'codigo' => $codigo,
                'nombre' => $nombres[$codigo],
                'soporte' => $soporte,
                'predichos' => $predichos,
                'precision' => $precision === null ? null : round($precision, 4),
                'recall' => $recall === null ? null : round($recall, 4),
                'f1' => $f1 === null ? null : round($f1, 4),
            ];
        }

        $media = function (string $clave) use ($porClase) {
            $v = array_filter(array_column($porClase, $clave), fn ($x) => $x !== null);

            return $v === [] ? null : round(array_sum($v) / count($v), 4);
        };

        return [
            [
                'filas_reales' => $codigos,
                'columnas_predichas' => $codigos,
                'nombres' => $nombres,
                'valores' => $valores,
            ],
            $porClase,
            [
                'accuracy' => $total > 0 ? round($aciertos / $total, 4) : null,
                'precision_macro' => $media('precision'),
                'recall_macro' => $media('recall'),
                'f1_macro' => $media('f1'),
                'muestras' => $total,
            ],
        ];
    }

    private function confianza(array $cnn): array
    {
        $prom = function (string $estado) use ($cnn) {
            $v = array_column(array_filter($cnn, fn ($f) => $f['estado'] === $estado), 'confianza');

            return [$v === [] ? null : round(array_sum($v) / count($v), 2), count($v)];
        };

        [$ok, $nOk] = $prom('Confirmado');
        [$mal, $nMal] = $prom('Corregido');
        [$rech, $nRech] = $prom('Rechazado');

        return [
            'promedio_aciertos' => $ok, 'n_aciertos' => $nOk,
            'promedio_errores' => $mal, 'n_errores' => $nMal,
            'promedio_rechazados' => $rech, 'n_rechazados' => $nRech,
        ];
    }

    private function evolucionMensual(array $cnn): array
    {
        $meses = [];

        foreach ($cnn as $f) {
            if (! in_array($f['estado'], ['Confirmado', 'Corregido', 'Rechazado'], true)) {
                continue;
            }

            $m = &$meses[$f['mes']];
            $m ??= ['mes' => $f['mes'], 'confirmados' => 0, 'corregidos' => 0, 'rechazados' => 0];
            $m[match ($f['estado']) {
                'Confirmado' => 'confirmados', 'Corregido' => 'corregidos', default => 'rechazados',
            }]++;
            unset($m);
        }

        ksort($meses);

        return array_values(array_map(function ($m) {
            $utiles = $m['confirmados'] + $m['corregidos'];

            return $m + [
                'validados' => $utiles + $m['rechazados'],
                'aciertos_pct' => $utiles > 0 ? round($m['confirmados'] / $utiles * 100, 1) : null,
            ];
        }, $meses));
    }
}
