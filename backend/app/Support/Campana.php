<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Campaña agrícola de una fecha. Único lugar del backend donde se calcula; el frontend
 * tiene su espejo en src/utils/campana.js y lee el mismo mes de inicio desde
 * GET /api/configuracion/agricola.
 */
class Campana
{
    public static function mesInicio(): int
    {
        return max(1, min(12, (int) config('agricola.mes_inicio_campana', 7)));
    }

    /** Primer día de la campaña a la que pertenece la fecha. */
    public static function inicioDe(CarbonInterface|string|null $fecha = null): Carbon
    {
        $fecha = Carbon::parse($fecha ?? now())->startOfDay();
        $anio = $fecha->month >= self::mesInicio() ? $fecha->year : $fecha->year - 1;

        return Carbon::create($anio, self::mesInicio(), 1)->startOfDay();
    }

    /** Último día de la campaña a la que pertenece la fecha. */
    public static function finDe(CarbonInterface|string|null $fecha = null): Carbon
    {
        return self::inicioDe($fecha)->copy()->addYear()->subDay()->startOfDay();
    }

    /** "2025–2026" (o "2026" si la campaña coincide con el año calendario). */
    public static function etiquetaDe(CarbonInterface|string|null $fecha = null): string
    {
        $inicio = self::inicioDe($fecha);

        return self::mesInicio() === 1 ? (string) $inicio->year : $inicio->year.'–'.($inicio->year + 1);
    }

    /** @return array{etiqueta:string, desde:string, hasta:string} */
    public static function resumenDe(CarbonInterface|string|null $fecha = null): array
    {
        return [
            'etiqueta' => self::etiquetaDe($fecha),
            'desde' => self::inicioDe($fecha)->toDateString(),
            'hasta' => self::finDe($fecha)->toDateString(),
        ];
    }
}
