<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campaña agrícola
    |--------------------------------------------------------------------------
    |
    | Mes en que empieza la campaña (1 a 12). Con 7, la "Campaña 2025–2026" va del
    | 1-jul-2025 al 30-jun-2026, alineada con el año agrícola tradicional del altiplano
    | (que inicia cerca del 21 de junio). Se cambia con AGRICOLA_MES_INICIO_CAMPANA en el .env.
    |
    */

    'mes_inicio_campana' => (int) env('AGRICOLA_MES_INICIO_CAMPANA', 7),

];
