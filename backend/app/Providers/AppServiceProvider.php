<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->definirLimitesDeAutenticacion();
    }

    /**
     * Límites por ruta y por IP. Con "throttle:10,1" todas las rutas compartían un solo contador
     * por IP (el reenvío de OTP quedaba bloqueado después de unos pocos intentos de otras rutas);
     * con limitadores con nombre cada ruta tiene su propio contador.
     */
    private function definirLimitesDeAutenticacion(): void
    {
        $porIp = fn (int $maximo) => fn (Request $request) => Limit::perMinute($maximo)->by($request->ip());

        RateLimiter::for('auth-login', $porIp(10));
        RateLimiter::for('auth-otp', $porIp(10));
        RateLimiter::for('auth-reenvio', $porIp(3));
        RateLimiter::for('auth-rostro', $porIp(10));
        RateLimiter::for('auth-rostro-otp', $porIp(5));

        // Buscador general: 60 consultas por minuto y por usuario.
        RateLimiter::for('buscar', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
