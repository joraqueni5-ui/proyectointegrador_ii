<?php

namespace App\Http\Controllers\Diagnostico;

use App\DTOs\Diagnostico\EnfermedadDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnostico\StoreEnfermedadRequest;
use App\Http\Requests\Diagnostico\UpdateEnfermedadRequest;
use App\Models\Enfermedad;
use App\Services\Diagnostico\EnfermedadService;
use Illuminate\Http\Request;

class EnfermedadController extends Controller
{
    public function __construct(
        private readonly EnfermedadService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, ['ver_diagnosticos', 'gestionar_enfermedades', 'ver_reportes']);

        return response()->json(
            $this->service->listar($request->all())
        );
    }

    public function show(Request $request, Enfermedad $enfermedad)
    {
        $this->checkPermiso($request, ['ver_diagnosticos', 'gestionar_enfermedades', 'ver_reportes']);

        return response()->json(
            $this->service->ver($enfermedad)
        );
    }

    public function store(StoreEnfermedadRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        $dto = EnfermedadDTO::fromRequest(
            $request->validated()
        );

        return response()->json(
            $this->service->crear($dto),
            201
        );
    }

    public function update(UpdateEnfermedadRequest $request, Enfermedad $enfermedad)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        $dto = EnfermedadDTO::fromRequest(
            array_merge($enfermedad->toArray(), $request->validated())
        );

        return response()->json(
            $this->service->actualizar($enfermedad, $dto)
        );
    }

    public function destroy(Request $request, Enfermedad $enfermedad)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        return response()->json(
            $this->service->desactivar($enfermedad)
        );
    }

    public function cambiarEstado(Request $request, Enfermedad $enfermedad)
    {
        $this->checkPermiso($request, 'gestionar_enfermedades');

        return response()->json(
            $this->service->cambiarEstado($enfermedad)
        );
    }
}
