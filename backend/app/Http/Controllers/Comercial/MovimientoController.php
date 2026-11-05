<?php

namespace App\Http\Controllers\Comercial;

use App\DTOs\Comercial\MovimientoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comercial\StoreMovimientoRequest;
use App\Services\Comercial\MovimientoService;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function __construct(
        private readonly MovimientoService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_inventario');

        return response()->json(
            $this->service->listar($request->all())
        );
    }

    public function store(StoreMovimientoRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_inventario');

        $data = $request->validated();
        $data['id_usuario'] = $request->user()->id;

        $dto = MovimientoDTO::fromRequest($data);

        return response()->json(
            $this->service->registrar($dto),
            201
        );
    }
}
