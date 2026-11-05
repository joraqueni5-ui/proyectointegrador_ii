<?php

namespace App\Http\Controllers\Agricola;

use App\DTOs\Agricola\CultivoVariedadDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agricola\CambiarEstadoCultivoRequest;
use App\Http\Requests\Agricola\StoreCultivoVariedadRequest;
use App\Http\Requests\Agricola\UpdateCultivoVariedadRequest;
use App\Models\CultivoVariedad;
use App\Services\Agricola\CultivoVariedadService;
use Illuminate\Http\Request;

class CultivoVariedadController extends Controller
{
    public function __construct(
        private readonly CultivoVariedadService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        return response()->json(
            $this->service->listar($request)
        );
    }

    public function show(Request $request, CultivoVariedad $cultivoVariedad)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        return response()->json(
            $this->service->verDetalle($cultivoVariedad)
        );
    }

    public function store(StoreCultivoVariedadRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $dto = CultivoVariedadDTO::fromRequest($request->validated());

        $cultivo = $this->service->crear($dto);

        return response()->json($cultivo, 201);
    }

    public function update(UpdateCultivoVariedadRequest $request, CultivoVariedad $cultivoVariedad)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $dto = CultivoVariedadDTO::fromRequest(
            array_merge($cultivoVariedad->toArray(), $request->validated())
        );

        $cultivo = $this->service->actualizar($cultivoVariedad, $dto);

        return response()->json($cultivo);
    }

    public function cambiarEstado(CambiarEstadoCultivoRequest $request, CultivoVariedad $cultivoVariedad)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $data = $request->validated();

        if ($data['estado'] === 'Activo' && $cultivoVariedad->estado !== 'Activo') {
            $error = match (true) {
                $cultivoVariedad->cosechas()->exists() => 'No se puede reactivar un cultivo que ya tiene cosecha registrada.',
                $cultivoVariedad->parcela?->estado !== 'Activa' => 'No se puede reactivar el cultivo porque la parcela no está activa.',
                CultivoVariedad::where('id_parcela', $cultivoVariedad->id_parcela)
                    ->where('estado', 'Activo')
                    ->exists() => 'La parcela ya tiene otro cultivo activo.',
                default => null,
            };

            if ($error) {
                return response()->json(['message' => $error], 422);
            }
        }

        if (in_array($data['estado'], ['Perdido', 'Abandonado'], true)) {
            $cultivoVariedad->loadCount('cosechas');

            if ($cultivoVariedad->cosechas_count > 0) {
                return response()->json([
                    'message' => 'No se puede marcar el cultivo como perdido o abandonado porque ya tiene cosechas registradas.',
                ], 403);
            }
        }

        $cultivo = $this->service->cambiarEstado(
            $cultivoVariedad,
            $data['estado']
        );

        return response()->json([
            'message' => 'Estado del cultivo actualizado correctamente.',
            'cultivo' => $cultivo,
        ]);
    }

    public function destroy(Request $request, CultivoVariedad $cultivoVariedad)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $cultivo = $this->service->cambiarEstado($cultivoVariedad, 'Cosechado');

        return response()->json([
            'message' => 'Cultivo finalizado correctamente.',
            'cultivo' => $cultivo,
        ]);
    }
}
