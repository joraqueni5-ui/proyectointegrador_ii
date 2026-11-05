<?php

namespace App\Http\Controllers\Comercial;

use App\DTOs\Comercial\AlmacenDTO;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comercial\StoreAlmacenRequest;
use App\Http\Requests\Comercial\UpdateAlmacenRequest;
use App\Models\Almacen;
use App\Services\Comercial\AlmacenService;
use Illuminate\Http\Request;

class AlmacenController extends Controller
{
    public function __construct(
        private readonly AlmacenService $service
    ) {}

    // GET /api/almacenes
    public function index(Request $request)
    {
        $this->checkPermiso($request, 'ver_inventario');

        return response()->json(
            $this->service->listar($request->all())
        );
    }

    // GET /api/almacenes/{almacen}
    public function show(Request $request, Almacen $almacen)
    {
        $this->checkPermiso($request, 'ver_inventario');

        return response()->json($this->service->ver($almacen));
    }

    // POST /api/almacenes
    public function store(StoreAlmacenRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_almacenes');

        $dto = AlmacenDTO::fromRequest(
            $request->validated(),
            $request->user()->id
        );

        return response()->json(
            $this->service->crear($dto),
            201
        );
    }

    // PUT /api/almacenes/{almacen}
    public function update(UpdateAlmacenRequest $request, Almacen $almacen)
    {
        $this->checkPermiso($request, 'gestionar_almacenes');

        if ($almacen->estado === 'Activo' && $request->input('estado') === 'Inactivo') {
            $this->validarSinStock($almacen);
        }

        // Sin responsable por defecto: al editar se respeta el valor enviado (o el actual).
        $dto = AlmacenDTO::fromRequest(
            array_merge($almacen->toArray(), $request->validated())
        );

        return response()->json(
            $this->service->actualizar($almacen, $dto)
        );
    }

    // DELETE /api/almacenes/{almacen}
    public function destroy(Request $request, Almacen $almacen)
    {
        $this->checkPermiso($request, 'gestionar_almacenes');

        $this->validarSinStock($almacen);

        $this->service->desactivar($almacen);

        return response()->json(['message' => 'Almacén desactivado correctamente']);
    }

    public function cambiarEstado(Request $request, Almacen $almacen)
    {
        $this->checkPermiso($request, 'gestionar_almacenes');

        if ($almacen->estado === 'Activo') {
            $this->validarSinStock($almacen);
        }

        return response()->json(
            $this->service->cambiarEstado($almacen)
        );
    }

    private function validarSinStock(Almacen $almacen): void
    {
        if ((float) $almacen->inventarios()->sum('stock_actual') > 0) {
            throw new ReglaNegocioException('No se puede desactivar el almacén porque tiene stock registrado. Primero debe mover o regularizar el inventario.');
        }
    }
}
