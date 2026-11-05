<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP de autenticación
    |--------------------------------------------------------------------------
    |
    | OTP_DEBUG solo se utiliza en entorno local. Aunque se active por error en
    | producción, el servicio no expondrá el código fuera de APP_ENV=local.
    |
    */
    'debug' => env('OTP_DEBUG', false),
    'expires_minutes' => (int) env('OTP_EXPIRES_MINUTES', 15),
];
