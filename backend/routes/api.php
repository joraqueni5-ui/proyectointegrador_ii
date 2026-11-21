<?php

use App\Http\Controllers\Agricola\ComunidadController;
use App\Http\Controllers\Agricola\CosechaController;
use App\Http\Controllers\Agricola\CultivoVariedadController;
// Agrícola
use App\Http\Controllers\Agricola\ParcelaController;
use App\Http\Controllers\Agricola\ProductorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Comercial\AlmacenController;
use App\Http\Controllers\Comercial\CotizacionController;
// Diagnóstico
use App\Http\Controllers\Comercial\InventarioController;
use App\Http\Controllers\Comercial\MovimientoController;
use App\Http\Controllers\Comercial\ProductoController;
use App\Http\Controllers\Comercial\ReporteController;
use App\Http\Controllers\Comercial\VentaController;
use App\Http\Controllers\DashboardController;
// Comercial / Inventario
use App\Http\Controllers\Diagnostico\AlertaController;
use App\Http\Controllers\Diagnostico\DatasetValidadoController;
use App\Http\Controllers\Diagnostico\DesempenoModeloController;
use App\Http\Controllers\Diagnostico\DiagnosticoController;
use App\Http\Controllers\Diagnostico\EnfermedadController;
use App\Http\Controllers\Diagnostico\InspeccionController;
use App\Http\Controllers\Diagnostico\ProductoRecomendadoController;
use App\Http\Controllers\Diagnostico\ReporteFitosanitarioController;
use App\Http\Controllers\BuscarController;
use App\Http\Controllers\UserController;
use App\Models\EtapaFenologica;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth-otp');
Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:auth-reenvio');
Route::post('/verify-face', [AuthController::class, 'verifyFace'])->middleware('throttle:auth-rostro');
Route::post('/verify-face/usar-otp', [AuthController::class, 'usarOtpTrasRostro'])->middleware('throttle:auth-rostro-otp');

/*
|--------------------------------------------------------------------------
| Rutas protegidas
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'usuario.activo'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Autenticación / Perfil
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/buscar', BuscarController::class)->middleware('throttle:buscar');

    Route::get('/version', function () {
        return response()->json([
            'data' => [
                'appVersion' => config('app.version'),
                'apiVersion' => config('app.api_version'),
                'cnnVersion' => config('app.cnn_version'),
                'environment' => app()->environment(),
            ],
            'message' => 'BACKEND DEL SISTEMA PROINPA',
            'error' => null,
            'responseCode' => 100,
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Administración - Usuarios y roles
    |--------------------------------------------------------------------------
    */

    Route::get('/roles', [UserController::class, 'roles']);

    Route::get('/users/tecnicos-inspeccion', [UserController::class, 'tecnicosInspeccion']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show'])->whereNumber('id');
    Route::put('/users/{id}', [UserController::class, 'update'])->whereNumber('id');
    Route::patch('/users/{id}/activar', [UserController::class, 'activar'])->whereNumber('id');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::prefix('dashboard')->group(function () {
        Route::get('kpis', [DashboardController::class, 'kpis']);
        Route::get('mapa-parcelas', [DashboardController::class, 'mapaParcelas']);
        Route::get('alertas-recientes', [DashboardController::class, 'alertasRecientes']);
        Route::get('inspecciones-recientes', [DashboardController::class, 'inspeccionesRecientes']);
        Route::get('grafico-enfermedades', [DashboardController::class, 'graficoEnfermedades']);
        Route::get('grafico-cosechas', [DashboardController::class, 'graficoCosechas']);
    });

    /*
    |--------------------------------------------------------------------------
    | Módulo agrícola
    |--------------------------------------------------------------------------
    */

    Route::patch('comunidades/{comunidad}/activar', [ComunidadController::class, 'activar']);
    Route::apiResource('comunidades', ComunidadController::class)
        ->parameters(['comunidades' => 'comunidad']);

    Route::patch('productores/{productor}/activar', [ProductorController::class, 'activar']);
    Route::apiResource('productores', ProductorController::class)
        ->parameters(['productores' => 'productor']);

    Route::patch('parcelas/{parcela}/estado', [ParcelaController::class, 'cambiarEstado']);
    Route::get('parcelas/{parcela}/historial-fitosanitario', [ParcelaController::class, 'historialFitosanitario']);
    Route::apiResource('parcelas', ParcelaController::class)
        ->except(['destroy'])
        ->parameters(['parcelas' => 'parcela']);

    Route::patch('cultivo-variedades/{cultivoVariedad}/estado', [CultivoVariedadController::class, 'cambiarEstado']);
    Route::apiResource('cultivo-variedades', CultivoVariedadController::class)
        ->parameters(['cultivo-variedades' => 'cultivoVariedad']);

    Route::get('etapas-fenologicas', function () {
        return response()->json(
            EtapaFenologica::orderBy('orden')
                ->get(['id', 'nombre', 'codigo', 'descripcion', 'orden'])
        );
    });

    Route::get('cosechas/papelera', [CosechaController::class, 'papelera']);
    Route::patch('cosechas/{id}/restaurar', [CosechaController::class, 'restaurar'])->whereNumber('id');
    Route::apiResource('cosechas', CosechaController::class)
        ->parameters(['cosechas' => 'cosecha']);

    /*
    |--------------------------------------------------------------------------
    | Diagnóstico fitosanitario
    |--------------------------------------------------------------------------
    */

    Route::get('inspecciones/resumen', [InspeccionController::class, 'resumen']);
    Route::patch('inspecciones/{inspeccion}/estado', [InspeccionController::class, 'actualizarEstado']);
    Route::apiResource('inspecciones', InspeccionController::class)
        ->except(['destroy'])
        ->parameters(['inspecciones' => 'inspeccion']);

    Route::get('diagnosticos/cnn/estado', [DiagnosticoController::class, 'estadoCnn']);
    Route::get('diagnosticos/desempeno-modelo', [DesempenoModeloController::class, 'index']);
    Route::get('diagnosticos/desempeno-modelo/pdf', [DesempenoModeloController::class, 'pdf']);
    Route::get('diagnosticos/exportar-dataset', [DatasetValidadoController::class, 'exportar']);
    Route::patch('diagnosticos/{diagnostico}/validar', [DiagnosticoController::class, 'validar']);
    Route::apiResource('diagnosticos', DiagnosticoController::class)
        ->only(['index', 'show', 'store'])
        ->parameters(['diagnosticos' => 'diagnostico']);

    Route::get('alertas/no-leidas', [AlertaController::class, 'noLeidas']);
    Route::get('alertas/estadisticas', [AlertaController::class, 'estadisticas']);
    Route::patch('alertas/{alerta}/leer', [AlertaController::class, 'marcarLeida']);
    Route::patch('alertas/{alerta}/atender', [AlertaController::class, 'atender']);
    Route::patch('alertas/{alerta}/planificar', [AlertaController::class, 'planificar']);
    Route::patch('alertas/{alerta}/iniciar-tratamiento', [AlertaController::class, 'iniciarTratamiento']);
    Route::patch('alertas/{alerta}/seguimiento', [AlertaController::class, 'registrarSeguimiento']);
    Route::patch('alertas/{alerta}/resolver', [AlertaController::class, 'resolver']);
    Route::apiResource('alertas', AlertaController::class)
        ->only(['index', 'show'])
        ->parameters(['alertas' => 'alerta']);

    Route::patch('enfermedades/{enfermedad}/estado', [EnfermedadController::class, 'cambiarEstado']);
    Route::apiResource('enfermedades', EnfermedadController::class)
        ->parameters(['enfermedades' => 'enfermedad']);

    Route::prefix('enfermedades/{enfermedad}/productos-recomendados')->group(function () {
        Route::get('/', [ProductoRecomendadoController::class, 'index']);
        Route::post('/', [ProductoRecomendadoController::class, 'store']);
        Route::put('{producto}', [ProductoRecomendadoController::class, 'update']);
        Route::delete('{producto}', [ProductoRecomendadoController::class, 'destroy']);
        Route::delete('{producto}/force', [ProductoRecomendadoController::class, 'forceDelete']);
    });

    Route::prefix('reportes-fitosanitarios')->group(function () {
        Route::get('/', [ReporteFitosanitarioController::class, 'preview']);
        Route::get('excel', [ReporteFitosanitarioController::class, 'excel']);
        Route::get('pdf', [ReporteFitosanitarioController::class, 'pdf']);
    });

    /*
    |--------------------------------------------------------------------------
    | Comercial / Inventario
    |--------------------------------------------------------------------------
    */

    Route::prefix('inventario')->group(function () {
        Route::get('/', [InventarioController::class, 'index']);
        Route::get('dashboard', [InventarioController::class, 'dashboard']);
        Route::get('alertas', [InventarioController::class, 'alertas']);

        Route::patch('productos/{producto}/estado', [ProductoController::class, 'cambiarEstado']);
        Route::apiResource('productos', ProductoController::class)
            ->parameters(['productos' => 'producto']);

        Route::patch('almacenes/{almacen}/estado', [AlmacenController::class, 'cambiarEstado']);
        Route::apiResource('almacenes', AlmacenController::class)
            ->parameters(['almacenes' => 'almacen']);

        Route::apiResource('movimientos', MovimientoController::class)
            ->only(['index', 'store']);
    });

    Route::get('ventas/dashboard', [VentaController::class, 'dashboard']);
    Route::get('ventas/tendencia', [VentaController::class, 'tendencia']);
    Route::patch('ventas/{factura}/estado', [VentaController::class, 'cambiarEstado']);
    Route::apiResource('ventas', VentaController::class)
        ->only(['index', 'show', 'store'])
        ->parameters(['ventas' => 'factura']);

    Route::patch('cotizaciones/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar']);
    Route::patch('cotizaciones/{cotizacion}/rechazar', [CotizacionController::class, 'rechazar']);
    Route::patch('cotizaciones/{cotizacion}/anular', [CotizacionController::class, 'anular']);
    Route::post('cotizaciones/{cotizacion}/convertir-venta', [CotizacionController::class, 'convertirVenta']);
    Route::apiResource('cotizaciones', CotizacionController::class)
        ->only(['index', 'show', 'store'])
        ->parameters(['cotizaciones' => 'cotizacion']);

    Route::prefix('reportes')->group(function () {
        Route::get('/', [ReporteController::class, 'preview']);
        Route::get('excel', [ReporteController::class, 'excel']);
        Route::get('pdf', [ReporteController::class, 'pdf']);
    });
});
