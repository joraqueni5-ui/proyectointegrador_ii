<?php

namespace App\Http\Controllers\Comercial;

use App\DTOs\Comercial\CotizacionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comercial\RechazarCotizacionRequest;
use App\Http\Requests\Comercial\StoreCotizacionRequest;
use App\Models\Cotizacion;
use App\Services\Comercial\CotizacionService;
use App\Support\Paginacion;
use Illuminate\Http\Request;

class CotizacionController extends Controller
{
    public function __construct(
        private readonly CotizacionService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_cotizaciones');

        return response()->json(
            $this->service->listar(
                busqueda: $request->query('busqueda', ''),
                estado: $request->query('estado'),
                perPage: Paginacion::porPagina($request->query('per_page')),
            )
        );
    }

    public function show(Request $request, Cotizacion $cotizacion)
    {
        $this->checkPermiso($request, 'gestionar_cotizaciones');

        return response()->json(
            $this->service->ver($cotizacion)
        );
    }

    public function store(StoreCotizacionRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_cotizaciones');

        $data = $request->validated();
        $data['id_usuario'] = $request->user()->id;

        $dto = CotizacionDTO::fromRequest($data);

        return response()->json(
            $this->service->crear($dto),
            201
        );
    }

    public function aprobar(Request $request, Cotizacion $cotizacion)
    {
        $this->checkPermiso($request, 'gestionar_cotizaciones');

        return response()->json(
            $this->service->aprobar($cotizacion, $request->user()->id)
        );
    }

    public function rechazar(RechazarCotizacionRequest $request, Cotizacion $cotizacion)
    {
        $this->checkPermiso($request, 'gestionar_cotizaciones');

        return response()->json(
            $this->service->rechazar(
                $cotizacion,
                $request->user()->id,
                $request->validated()['motivo_rechazo']
            )
        );
    }

    public function anular(Request $request, Cotizacion $cotizacion)
    {
        $this->checkPermiso($request, 'gestionar_cotizaciones');

        return response()->json(
            $this->service->anular($cotizacion)
        );
    }

    public function convertirVenta(Request $request, Cotizacion $cotizacion)
    {
        $this->checkPermiso($request, 'gestionar_cotizaciones');

        return response()->json(
            $this->service->convertirVenta($cotizacion, $request->user()->id)
        );
    }
}
