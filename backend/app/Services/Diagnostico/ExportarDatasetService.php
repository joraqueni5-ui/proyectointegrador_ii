<?php

namespace App\Services\Diagnostico;

use App\Exceptions\ReglaNegocioException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Exporta el dataset validado para reentrenar la CNN (ciclo de retroalimentación).
 *
 * Incluye las fotos de los diagnósticos Confirmados y Corregidos, en carpetas según la
 * enfermedad FINAL (la que validó el agrónomo), con los mismos nombres de clase que espera
 * cnn_service/training_v2/train_v2.py. Es solo lectura.
 *
 * Los diagnósticos cuya enfermedad final no es una de las 3 clases de la CNN (por ejemplo
 * Rizoctoniasis) o cuya foto ya no existe en disco no se incluyen; se cuentan en el LEEME.
 */
class ExportarDatasetService
{
    /** Código de enfermedad del sistema -> nombre de clase que usa el entrenamiento. */
    public const CLASES = [
        'CNN-TTP' => 'early_blight',
        'CNN-SANA' => 'healthy',
        'CNN-TT' => 'late_blight',
    ];

    /**
     * @param  array{fecha_desde?: ?string, fecha_hasta?: ?string, modelo_version?: ?string}  $filtros
     * @return array{ruta: string, fotos: int, por_clase: array<string,int>, omitidos: array<string,int>}
     */
    public function generar(array $filtros = []): array
    {
        $filas = $this->consultar($filtros);

        if ($filas->isEmpty()) {
            throw new ReglaNegocioException(
                'Todavía no hay diagnósticos confirmados o corregidos para exportar.'
            );
        }

        $disco = Storage::disk('public');
        $ruta = tempnam(sys_get_temp_dir(), 'dataset_');
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP temporal.');
        }

        $csv = [$this->fila([
            'id_diagnostico', 'archivo', 'clase_predicha', 'clase_final', 'estado_validacion',
            'confianza', 'version_modelo', 'fecha', 'comunidad', 'parcela',
        ])];
        $porClase = array_fill_keys(self::CLASES, 0);
        $omitidos = ['clase_fuera_de_la_cnn' => 0, 'foto_no_encontrada' => 0];

        foreach ($filas as $f) {
            $claseFinal = self::CLASES[$f->codigo_final] ?? null;

            if ($claseFinal === null) {
                $omitidos['clase_fuera_de_la_cnn']++;

                continue;
            }

            if (! $f->ruta_fotografia || ! $disco->exists($f->ruta_fotografia)) {
                $omitidos['foto_no_encontrada']++;

                continue;
            }

            $extension = strtolower(pathinfo($f->ruta_fotografia, PATHINFO_EXTENSION)) ?: 'jpg';
            $archivo = sprintf('%s/diag_%05d.%s', $claseFinal, $f->id, $extension);
            $zip->addFile($disco->path($f->ruta_fotografia), $archivo);

            $top3 = $f->top3_predicciones ? json_decode($f->top3_predicciones, true) : null;
            $predicha = self::CLASES[$top3[0]['codigo_enfermedad'] ?? ''] ?? ($top3 ? 'otra' : '');

            $csv[] = $this->fila([
                $f->id, $archivo, $predicha, $claseFinal, $f->estado_validacion,
                number_format((float) $f->porcentaje_confianza, 2, '.', ''), $f->modelo_version,
                Carbon::parse($f->created_at)->format('Y-m-d'), $f->comunidad, $f->codigo_parcela,
            ]);
            $porClase[$claseFinal]++;
        }

        $fotos = array_sum($porClase);

        if ($fotos === 0) {
            $zip->close();
            @unlink($ruta);

            throw new ReglaNegocioException(
                'Ningún diagnóstico validado se puede exportar: sus enfermedades no corresponden a las clases de la CNN '
                .'(Tizón temprano, Tizón tardío o Sana) o sus fotos ya no existen.'
            );
        }

        // UTF-8 con BOM: Excel abre bien las tildes.
        $zip->addFromString('metadata.csv', "\xEF\xBB\xBF".implode("\r\n", $csv)."\r\n");
        $zip->addFromString('LEEME.txt', $this->leeme($fotos, $porClase, $omitidos, $filtros));
        $zip->close();

        return ['ruta' => $ruta, 'fotos' => $fotos, 'por_clase' => $porClase, 'omitidos' => $omitidos];
    }

    private function consultar(array $filtros)
    {
        return DB::table('diagnosticos_cnn as d')
            ->join('enfermedades as e', 'e.id', '=', 'd.id_enfermedad')
            ->join('inspecciones as i', 'i.id', '=', 'd.id_inspeccion')
            ->join('parcelas as p', 'p.id', '=', 'i.id_parcela')
            ->join('productores as pr', 'pr.id', '=', 'p.id_productor')
            ->join('comunidades as c', 'c.id', '=', 'pr.id_comunidad')
            ->whereIn('d.estado_validacion', ['Confirmado', 'Corregido'])
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $f) => $q->where('d.created_at', '>=', Carbon::parse($f)->startOfDay()))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $f) => $q->where('d.created_at', '<=', Carbon::parse($f)->endOfDay()))
            ->when($filtros['modelo_version'] ?? null, fn ($q, $v) => $q->where('d.modelo_version', $v))
            ->orderBy('d.id')
            ->get([
                'd.id', 'd.ruta_fotografia', 'd.porcentaje_confianza', 'd.top3_predicciones', 'd.modelo_version',
                'd.estado_validacion', 'd.created_at', 'e.codigo_enfermedad as codigo_final',
                'c.nombre_comunidad as comunidad', 'p.codigo_parcela',
            ]);
    }

    private function fila(array $campos): string
    {
        return implode(',', array_map(function ($v) {
            $v = (string) $v;

            // Evita que Excel interprete un texto como fórmula.
            if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && ! is_numeric($v)) {
                $v = "'".$v;
            }

            return '"'.str_replace('"', '""', $v).'"';
        }, $campos));
    }

    private function leeme(int $fotos, array $porClase, array $omitidos, array $filtros): string
    {
        $lineas = [
            'DATASET VALIDADO - Sistema PROINPA',
            'Generado: '.now()->format('d/m/Y H:i'),
            '',
            'Contenido',
            "  Fotos: {$fotos}",
        ];

        foreach ($porClase as $clase => $n) {
            $lineas[] = "  {$clase}/: {$n}";
        }

        $lineas = array_merge($lineas, [
            '  metadata.csv: una fila por foto (diagnóstico, clase predicha por la IA, clase final validada, confianza,',
            '                versión del modelo, fecha, comunidad y parcela).',
            '',
            'Filtros: desde '.($filtros['fecha_desde'] ?? 'sin límite').', hasta '.($filtros['fecha_hasta'] ?? 'sin límite')
                .', modelo '.($filtros['modelo_version'] ?? 'todos'),
            '',
            'No incluidos',
            "  Enfermedad final fuera de las 3 clases de la CNN: {$omitidos['clase_fuera_de_la_cnn']}",
            "  Foto no encontrada en el servidor: {$omitidos['foto_no_encontrada']}",
            '',
            'Para reentrenar la CNN con este dataset: ver cnn_service/training_v2/README.md (sección "Ciclo de retroalimentación").',
            'Importante: varias fotos de una misma parcela se parecen entre sí; al dividir en entrenamiento/validación/prueba',
            'agrupe por la columna "parcela" para no mezclar fotos casi idénticas entre conjuntos.',
        ]);

        return implode("\r\n", $lineas)."\r\n";
    }
}
