<?php

namespace App\Support;

class Paginacion
{
    /**
     * Normaliza el parámetro per_page para evitar consultas excesivas.
     */
    public static function porPagina(mixed $valor, int $defecto = 15): int
    {
        $porPagina = (int) ($valor ?? $defecto);

        return min(max($porPagina, 1), 100);
    }
}
