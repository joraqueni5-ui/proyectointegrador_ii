<?php

namespace App\Http\Controllers\Diagnostico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnostico\DesempenoModeloRequest;
use App\Services\Diagnostico\DesempenoModeloService;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Desempeño de la CNN calculado con los diagnósticos ya validados (solo lectura).
 * Los permisos (ver_diagnosticos y ver_reportes) los comprueba DesempenoModeloRequest.
 */
class DesempenoModeloController extends Controller
{
    public function __construct(
        private readonly DesempenoModeloService $service
    ) {}

    public function index(DesempenoModeloRequest $request)
    {
        return response()->json(
            $this->service->calcular($request->validated())
        );
    }

    public function pdf(DesempenoModeloRequest $request)
    {
        $filtros = $request->validated();

        $pdf = Pdf::loadView('reportes.desempeno_modelo_pdf', [
            'datos' => $this->service->calcular($filtros),
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
            'generadoPor' => $request->user()->name,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('desempeno_modelo_'.now()->format('Ymd_His').'.pdf');
    }
}
