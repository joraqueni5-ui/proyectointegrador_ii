<?php

namespace App\Http\Controllers\Agricola;

use App\DTOs\Agricola\CosechaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agricola\StoreCosechaRequest;
use App\Http\Requests\Agricola\UpdateCosechaRequest;
use App\Models\Cosecha;
use App\Services\Agricola\CosechaService;
use Illuminate\Http\Request;

class CosechaController extends Controller
{
    public function __construct(
        private readonly CosechaService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'registrar_cosechas');

        return response()->json(
            $this->service->listar($request)
        );
    }

    public function papelera(Request $request)
    {
        $this->checkPermiso($request, 'registrar_cosechas');

        return response()->json(
            $this->service->listarPapelera($request)
        );
    }

    public function store(StoreCosechaRequest $request)
    {
        $this->checkPermiso($request, 'registrar_cosechas');

        $dto = CosechaDTO::fromRequest($request->validated());

        $cosecha = $this->service->crear($dto, $request->user()->id);

        return response()->json($cosecha, 201);
    }

    public function show(Request $request, Cosecha $cosecha)
    {
        $this->checkPermiso($request, 'registrar_cosechas');

        return response()->json(
            $this->service->verDetalle($cosecha)
        );
    }

    public function update(UpdateCosechaRequest $request, Cosecha $cosecha)
    {
        $this->checkPermiso($request, 'registrar_cosechas');

        $dto = CosechaDTO::fromRequest(
            array_merge($cosecha->toArray(), $request->validated())
        );

        $cosecha = $this->service->actualizar($cosecha, $dto);

        return response()->json($cosecha);
    }

    public function destroy(Request $request, Cosecha $cosecha)
    {
        $this->checkPermiso($request, 'registrar_cosechas');

        $this->service->eliminar($cosecha);

        return response()->json([
            'message' => 'Cosecha enviada a papelera correctamente.',
        ]);
    }

    public function restaurar(Request $request, int $id)
    {
        $this->checkPermiso($request, 'registrar_cosechas');

        $cosecha = $this->service->restaurar($id);

        return response()->json([
            'message' => 'Cosecha restaurada correctamente.',
            'cosecha' => $cosecha,
        ]);
    }
}
