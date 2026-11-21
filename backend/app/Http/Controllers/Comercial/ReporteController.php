<?php

namespace App\Http\Controllers\Comercial;

use App\Exports\Comercial\ReporteExport;
use App\Http\Controllers\Controller;
use App\Services\Comercial\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteService $service
    ) {}

    public function preview(Request $request)
    {
        $this->checkPermisoReporte($request);

        $data = $this->validarReporte($request);

        $tipo = $data['tipo'];
        unset($data['tipo']);

        return response()->json(
            $this->service->generar($tipo, $data)
        );
    }

    public function excel(Request $request)
    {
        $this->checkPermisoReporte($request);

        $data = $this->validarReporte($request);

        $tipo = $data['tipo'];
        unset($data['tipo']);

        $reporte = $this->service->generar($tipo, $data);

        $filename = 'reporte_'.$tipo.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new ReporteExport($reporte, $data),
            $filename
        );
    }

    public function pdf(Request $request)
    {
        $this->checkPermisoReporte($request);

        $data = $this->validarReporte($request);

        $tipo = $data['tipo'];
        unset($data['tipo']);

        $reporte = $this->service->generar($tipo, $data);

        $pdf = Pdf::loadView('reportes.comercial_pdf', [
            'reporte' => $reporte,
            'filtros' => $data,
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
        ])->setPaper('letter', 'landscape');

        $filename = 'reporte_'.$tipo.'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    private function checkPermisoReporte(Request $request): void
    {
        $this->checkPermiso(
            $request,
            ['gestionar_ventas', 'gestionar_inventario'],
            'No tienes permiso para generar reportes.'
        );
    }

    private function validarReporte(Request $request): array
    {
        $datos = $request->validate([
            'tipo' => [
                'required',
                'in:productos,inventario,movimientos,ventas,cotizaciones,alertas_stock',
            ],

            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],

            'id_producto' => ['nullable', 'integer'],
            'id_almacen' => ['nullable', 'integer'],
            'id_productor' => ['nullable', 'integer'],
            'id_usuario' => ['nullable', 'integer'],
            'id_responsable' => ['nullable', 'integer'],
            'id_aprobador' => ['nullable', 'integer'],

            'categoria' => ['nullable', 'string'],
            'estado' => ['nullable', 'string'],
            'busqueda' => ['nullable', 'string'],
            'metodo_pago' => ['nullable', 'string'],
            'tipo_movimiento' => ['nullable', 'string'],
            'motivo' => ['nullable', 'string'],

            'monto_min' => ['nullable', 'numeric', 'min:0'],
            'monto_max' => ['nullable', 'numeric', 'min:0', 'gte:monto_min'],
        ]);

        if (! empty($datos['fecha_inicio']) && ! empty($datos['fecha_fin'])
            && \Illuminate\Support\Carbon::parse($datos['fecha_inicio'])->diffInDays(\Illuminate\Support\Carbon::parse($datos['fecha_fin'])) > 1096) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fecha_fin' => 'El rango del reporte no puede superar los 3 años. Acorta las fechas.',
            ]);
        }

        return $datos;
    }
}
