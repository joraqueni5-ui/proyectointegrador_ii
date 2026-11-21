<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->alias([
            'usuario.activo' => \App\Http\Middleware\UsuarioActivo::class,
        ]);

        // Sin sesión, las rutas api/* responden 401 en JSON (no intentan redirigir a una ruta "login" que no existe).
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => $request->is('api/*') ? null : url('/'));

        $middleware->encryptCookies(except: [
            'trusted_device_id',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Toda respuesta de error de api/* es JSON aunque la petición no traiga "Accept: application/json".
        $exceptions->shouldRenderJsonWhen(
            fn (\Illuminate\Http\Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (\App\Exceptions\ImagenRechazadaException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'motivo' => [
                    'codigo' => $exception->codigo(),
                    'consejo' => $exception->consejo(),
                ],
            ], $exception->estado());
        });

        $exceptions->render(function (\App\Exceptions\ReglaNegocioException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        });

        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $exception,
            \Illuminate\Http\Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'No autenticado.',
                ], 401);
            }
        });
    })->create();
