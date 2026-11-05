<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Services\Comercial\InventarioService;
use App\Support\Paginacion;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function __construct(
        private readonly InventarioService $service
    ) {}

    public function dashboard(Request $request)
    {
        $this->checkPermiso($request, 'ver_inventario');

        return response()->json($this->service->dashboard());
    }

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'ver_inventario');

        return response()->json(
            $this->service->listarInventario(Paginacion::porPagina($request->query('per_page'), 20))
        );
    }

    public function alertas(Request $request)
    {
        $this->checkPermiso($request, 'ver_alertas_stock');

        return response()->json($this->service->alertasStock());
    }
}
