<?php

namespace App\Http\Controllers\Diagnostico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnostico\ExportarDatasetRequest;
use App\Services\Diagnostico\ExportarDatasetService;

/**
 * Descarga el dataset validado (fotos por clase final + metadata.csv) para reentrenar la CNN.
 * Solo lectura. El control de rol lo hace ExportarDatasetRequest.
 */
class DatasetValidadoController extends Controller
{
    public function __construct(
        private readonly ExportarDatasetService $service
    ) {}

    public function exportar(ExportarDatasetRequest $request)
    {
        // Con muchas fotos el armado del ZIP puede tardar.
        set_time_limit(300);

        $resultado = $this->service->generar($request->validated());

        return response()
            ->download($resultado['ruta'], 'dataset_validado_'.now()->format('Ymd_His').'.zip', [
                'Content-Type' => 'application/zip',
                'X-Total-Fotos' => (string) $resultado['fotos'],
            ])
            ->deleteFileAfterSend(true);
    }
}
