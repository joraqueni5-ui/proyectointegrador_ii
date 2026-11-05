<?php

namespace App\Http\Controllers\Diagnostico;

use App\Exports\Comercial\ReporteExport;
use App\Http\Controllers\Controller;
use App\Services\Diagnostico\ReporteFitosanitarioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReporteFitosanitarioController extends Controller
{
    public function __construct(
        private readonly ReporteFitosanitarioService $service
    ) {}

    public function preview(Request $request)
    {
        $this->checkPermisoReporte($request);

        $data = $this->validarReporte($request);

        $tipo = $data['tipo_reporte'] ?? 'general';
        unset($data['tipo_reporte']);

        return response()->json(
            $this->service->generar($tipo, $data)
        );
    }

    public function excel(Request $request)
    {
        $this->checkPermisoReporte($request);

        $data = $this->validarReporte($request);

        $tipo = $data['tipo_reporte'] ?? 'general';
        unset($data['tipo_reporte']);

        $reporte = $this->service->generar($tipo, $data);

        $filename = 'reporte_fitosanitario_'.$tipo.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new ReporteExport($reporte, $data),
            $filename
        );
    }

    public function pdf(Request $request)
    {
        $this->checkPermisoReporte($request);

        $data = $this->validarReporte($request);

        $tipo = $data['tipo_reporte'] ?? 'general';
        unset($data['tipo_reporte']);

        $reporte = $this->service->generar($tipo, $data);

        $pdf = Pdf::loadView('reportes.fitosanitario_pdf', [
            'reporte' => $reporte,
            'filtros' => $data,
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'reporte_fitosanitario_'.$tipo.'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    private function validarReporte(Request $request): array
    {
        return $request->validate([
            'tipo_reporte' => [
                'nullable',
                'in:general,inspecciones,diagnosticos,por_parcela,por_tecnico,por_enfermedad,alertas,no_realizadas,pre_siembra',
            ],

            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],

            'id_parcela' => ['nullable', 'integer'],
            'id_productor' => ['nullable', 'integer'],
            'id_usuario' => ['nullable', 'integer'],
            'id_enfermedad' => ['nullable', 'integer'],

            'tipo_inspeccion' => ['nullable', 'in:Cultivo,Pre_siembra'],
            'estado_inspeccion' => ['nullable', 'string'],
            'nivel_severidad' => ['nullable', 'string'],
            'estado_validacion' => ['nullable', 'string'],
            'nivel_riesgo' => ['nullable', 'string'],
            'estado_alerta' => ['nullable', 'string'],

            'busqueda' => ['nullable', 'string'],
        ]);
    }

    private function checkPermisoReporte(Request $request): void
    {
        $this->checkPermiso(
            $request,
            ['ver_reportes', 'ver_inspecciones', 'ver_diagnosticos', 'ver_alertas'],
            'No tienes permiso para generar reportes fitosanitarios.'
        );
    }
}
