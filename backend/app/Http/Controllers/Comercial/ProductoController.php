<?php

namespace App\Http\Controllers\Comercial;

use App\DTOs\Comercial\ProductoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comercial\StoreProductoRequest;
use App\Http\Requests\Comercial\UpdateProductoRequest;
use App\Models\Producto;
use App\Services\Comercial\ProductoService;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct(
        private readonly ProductoService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'ver_inventario');

        return response()->json(
            $this->service->listar($request->all())
        );
    }

    public function show(Request $request, Producto $producto)
    {
        $this->checkPermiso($request, 'ver_inventario');

        return response()->json(
            $this->service->ver($producto)
        );
    }

    public function store(StoreProductoRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_inventario');

        $dto = ProductoDTO::fromRequest(
            $request->validated()
        );

        return response()->json(
            $this->service->crear($dto, $request->user()->id),
            201
        );
    }

    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $this->checkPermiso($request, 'gestionar_inventario');

        $dto = ProductoDTO::fromRequest(
            array_merge($producto->toArray(), $request->validated())
        );

        return response()->json(
            $this->service->actualizar($producto, $dto, $request->user()->id)
        );
    }

    public function cambiarEstado(Request $request, Producto $producto)
    {
        $this->checkPermiso($request, 'gestionar_inventario');

        return response()->json(
            $this->service->cambiarEstado($producto)
        );
    }

    public function destroy(Request $request, Producto $producto)
    {
        $this->checkPermiso($request, 'gestionar_inventario');

        return response()->json(
            $this->service->desactivar($producto)
        );
    }
}
