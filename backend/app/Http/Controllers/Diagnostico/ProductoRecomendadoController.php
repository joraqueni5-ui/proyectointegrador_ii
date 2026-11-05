<?php

namespace App\Http\Controllers\Diagnostico;

use App\DTOs\Diagnostico\ProductoRecomendadoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnostico\StoreProductoRecomendadoRequest;
use App\Http\Requests\Diagnostico\UpdateProductoRecomendadoRequest;
use App\Models\Enfermedad;
use App\Models\Producto;
use App\Services\Diagnostico\ProductoRecomendadoService;
use Illuminate\Http\Request;

class ProductoRecomendadoController extends Controller
{
    public function __construct(
        private readonly ProductoRecomendadoService $service
    ) {}

    public function index(Request $request, Enfermedad $enfermedad)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        return response()->json(
            $this->service->listar($enfermedad)
        );
    }

    public function store(StoreProductoRecomendadoRequest $request, Enfermedad $enfermedad)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        $dto = ProductoRecomendadoDTO::fromRequest(
            $request->validated()
        );

        return response()->json(
            $this->service->vincular($enfermedad, $dto),
            201
        );
    }

    public function update(
        UpdateProductoRecomendadoRequest $request,
        Enfermedad $enfermedad,
        Producto $producto
    ) {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        return response()->json(
            $this->service->actualizar(
                $enfermedad,
                $producto,
                $request->validated()
            )
        );
    }

    public function destroy(Request $request, Enfermedad $enfermedad, Producto $producto)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        $this->service->desactivar($enfermedad, $producto);

        return response()->json([
            'message' => 'Producto recomendado desactivado correctamente.',
        ]);
    }

    public function forceDelete(Request $request, Enfermedad $enfermedad, Producto $producto)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        $this->service->eliminar($enfermedad, $producto);

        return response()->json([
            'message' => 'Producto recomendado eliminado correctamente.',
        ]);
    }
}
