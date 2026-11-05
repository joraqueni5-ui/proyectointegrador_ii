<?php

use Illuminate\Support\Facades\Route;

// El backend solo expone la API; la interfaz es la aplicación Vue del frontend.
Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'api' => url('/api'),
]));
