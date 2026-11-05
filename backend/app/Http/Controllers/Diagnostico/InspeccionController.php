<?php

namespace App\Http\Controllers\Diagnostico;

use App\DTOs\Diagnostico\InspeccionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnostico\CambiarEstadoInspeccionRequest;
use App\Http\Requests\Diagnostico\StoreInspeccionRequest;
use App\Http\Requests\Diagnostico\UpdateInspeccionRequest;
use App\Models\Inspeccion;
use App\Services\Diagnostico\InspeccionService;
use Illuminate\Http\Request;

class InspeccionController extends Controller
{
    public function __construct(
        private readonly InspeccionService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'ver_inspecciones');

        $filtros = $request->validate([
            'id_parcela' => ['nullable', 'integer', 'exists:parcelas,id'],
            'id_usuario' => ['nullable', 'integer', 'exists:users,id'],
            'id_cultivo' => ['nullable', 'integer', 'exists:cultivo_variedades,id'],
            'tipo_inspeccion' => ['nullable', 'in:Cultivo,Pre_siembra'],
            'estado' => ['nullable', 'in:Pendiente,En_proceso,Completada,Validada,No_realizada'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'diagnostico_elegible' => ['nullable', 'boolean'],
            'atrasadas' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $inspecciones = $this->service->listar(
            filtros: $filtros,
            perPage: $filtros['per_page'] ?? 15,
        );

        return response()->json($inspecciones);
    }

    public function resumen(Request $request)
    {
        $this->checkPermiso($request, 'ver_inspecciones');

        return response()->json($this->service->resumen());
    }

    public function show(Request $request, Inspeccion $inspeccion)
    {
        $this->checkPermiso($request, 'ver_inspecciones');

        return response()->json(
            $this->service->verConDiagnosticos($inspeccion)
        );
    }

    public function store(StoreInspeccionRequest $request)
    {
        $this->checkPermiso($request, 'crear_inspecciones');

        $dto = InspeccionDTO::fromRequest(
            $request->validated(),
            $request->user()->id
        );

        $inspeccion = $this->service->crear($dto);

        return response()->json([
            'message' => 'Inspección registrada correctamente.',
            'inspeccion' => $inspeccion,
        ], 201);
    }

    public function update(UpdateInspeccionRequest $request, Inspeccion $inspeccion)
    {
        $this->checkPermiso($request, 'crear_inspecciones');

        $data = array_merge(
            $inspeccion->toArray(),
            $request->validated()
        );

        $dto = InspeccionDTO::fromRequest(
            $data,
            $inspeccion->id_programado_por ?? $request->user()->id
        );

        $inspeccion = $this->service->actualizar($inspeccion, $dto);

        return response()->json([
            'message' => 'Inspección actualizada correctamente.',
            'inspeccion' => $inspeccion,
        ]);
    }

    public function actualizarEstado(CambiarEstadoInspeccionRequest $request, Inspeccion $inspeccion)
    {
        $this->checkPermiso($request, 'crear_inspecciones');

        if ($request->validated()['estado'] === 'Validada') {
            $this->checkPermiso($request, 'validar_diagnosticos', 'No tienes permiso para validar inspecciones.');
        }

        $inspeccion = $this->service->actualizarEstado(
            $inspeccion,
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Estado de inspección actualizado correctamente.',
            'inspeccion' => $inspeccion,
        ]);
    }
}
