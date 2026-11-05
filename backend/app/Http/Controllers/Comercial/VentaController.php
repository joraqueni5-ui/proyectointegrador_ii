<?php

namespace App\Http\Controllers\Comercial;

use App\DTOs\Comercial\FacturaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comercial\CambiarEstadoVentaRequest;
use App\Http\Requests\Comercial\StoreVentaRequest;
use App\Models\Factura;
use App\Services\Comercial\VentaService;
use App\Support\Paginacion;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct(
        private readonly VentaService $service
    ) {}

    public function dashboard(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_ventas');

        return response()->json(
            $this->service->dashboard()
        );
    }

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_ventas');

        return response()->json(
            $this->service->listar(
                busqueda: $request->query('busqueda', ''),
                estado: $request->query('estado'),
                perPage: Paginacion::porPagina($request->query('per_page')),
            )
        );
    }

    public function show(Request $request, Factura $factura)
    {
        $this->checkPermiso($request, 'gestionar_ventas');

        return response()->json(
            $this->service->ver($factura)
        );
    }

    public function store(StoreVentaRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_ventas');

        $data = $request->validated();
        $data['id_usuario'] = $request->user()->id;

        $dto = FacturaDTO::fromRequest($data);

        return response()->json(
            $this->service->crear($dto),
            201
        );
    }

    public function cambiarEstado(CambiarEstadoVentaRequest $request, Factura $factura)
    {
        $this->checkPermiso($request, 'gestionar_ventas');

        $estado = $request->validated()['estado'];

        if ($estado === 'Anulada') {
            return response()->json(
                $this->service->anular($factura, $request->user()->id)
            );
        }

        return response()->json(
            $this->service->cambiarEstado($factura, $estado)
        );
    }

    public function tendencia(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_ventas');

        return response()->json(
            $this->service->tendenciaMensual(
                (int) $request->query('meses', 6)
            )
        );
    }
}
