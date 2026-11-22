<?php

namespace App\Http\Controllers\Agricola;

use App\DTOs\Agricola\ParcelaDTO;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agricola\CambiarEstadoParcelaRequest;
use App\Http\Requests\Agricola\StoreParcelaRequest;
use App\Http\Requests\Agricola\UpdateParcelaRequest;
use App\Models\Parcela;
use App\Services\Agricola\ParcelaService;
use Illuminate\Http\Request;

class ParcelaController extends Controller
{
    public function __construct(
        private readonly ParcelaService $service
    ) {}

    // GET /api/parcelas
    public function index(Request $request)
    {
        // También la usan el historial y los reportes fitosanitarios.
        $this->checkPermiso($request, ['gestionar_parcelas', 'ver_inspecciones', 'ver_diagnosticos', 'ver_alertas', 'ver_reportes']);

        $consulta = Parcela::conEtiqueta()->orderBy('parcelas.id', 'desc');

        // ?resumen=1: foto del último diagnóstico, alertas, última visita y cultivo activo (vista de Parcelas y mapas).
        if ($request->boolean('resumen')) {
            $consulta->conResumenSanitario()->with(['cultivoActivo.etapaFenologica:id,nombre,codigo,orden']);
        }

        $parcelas = $consulta->get();

        return response()->json($parcelas);
    }

    // GET /api/parcelas/siguiente-codigo?id_productor=ID  (código automático, solo para parcelas nuevas)
    public function siguienteCodigo(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $datos = $request->validate(['id_productor' => ['required', 'integer', 'exists:productores,id']], [
            'id_productor.required' => 'Elige primero al productor.',
            'id_productor.exists' => 'El productor seleccionado no existe.',
        ]);

        return response()->json($this->service->siguienteCodigo((int) $datos['id_productor']));
    }

    // GET /api/parcelas/{id}/linea-tiempo
    public function lineaTiempo(Request $request, Parcela $parcela)
    {
        $this->checkPermiso(
            $request,
            ['gestionar_parcelas', 'ver_inspecciones', 'ver_diagnosticos', 'ver_alertas'],
            'No tienes permiso para consultar el historial de la parcela.'
        );

        $filtros = $request->validate([
            'limite' => ['nullable', 'integer', 'min:1', 'max:200'],
            'tipos' => ['nullable', 'array'],
            'tipos.*' => ['in:siembra,inspeccion,diagnostico,alerta,cosecha'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ], [
            'tipos.*.in' => 'El tipo de evento seleccionado no es válido.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        return response()->json($this->service->lineaTiempo(
            $parcela,
            (int) ($filtros['limite'] ?? 60),
            $filtros['tipos'] ?? null,
            $filtros['desde'] ?? null,
            $filtros['hasta'] ?? null
        ));
    }

    // GET /api/parcelas/{id}
    public function show(Request $request, Parcela $parcela)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        return response()->json(
            $this->service->verDetalle($parcela)
        );
    }

    // POST /api/parcelas
    public function store(StoreParcelaRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $data = $request->validated();

        $dto = ParcelaDTO::fromRequest($data);

        $parcela = $this->service->crear($dto);

        return response()->json(
            $parcela->load('productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad'),
            201
        );
    }

    // PUT /api/parcelas/{id}
    public function update(UpdateParcelaRequest $request, Parcela $parcela)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $data = $request->validated();

        if (($data['estado'] ?? $parcela->estado) === 'Inactiva' && $parcela->estado !== 'Inactiva') {
            $this->validarSinCultivosActivos($parcela);
        }

        $dto = ParcelaDTO::fromRequest(
            array_merge($parcela->toArray(), $data)
        );

        $parcela = $this->service->actualizar($parcela, $dto);

        return response()->json(
            $parcela->load('productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad')
        );
    }

    // PATCH /api/parcelas/{id}/estado
    public function cambiarEstado(CambiarEstadoParcelaRequest $request, Parcela $parcela)
    {
        $this->checkPermiso($request, 'gestionar_parcelas');

        $data = $request->validated();

        if ($data['estado'] === 'Inactiva') {
            $this->validarSinCultivosActivos($parcela);
        }

        return response()->json(
            $this->service->cambiarEstado($parcela, $data['estado'])
        );
    }

    // GET /api/parcelas/{parcela}/historial-fitosanitario
    public function historialFitosanitario(Request $request, Parcela $parcela)
    {
        $this->checkPermiso(
            $request,
            ['gestionar_parcelas', 'ver_inspecciones', 'ver_diagnosticos', 'ver_alertas'],
            'No tienes permiso para consultar el historial fitosanitario.'
        );

        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'campania' => ['nullable', 'in:todas,primera,segunda'],
        ], [
            'anio.*' => 'El año seleccionado no es válido.',
            'campania.in' => 'La campaña seleccionada no es válida.',
        ]);

        return response()->json(
            $this->service->historialFitosanitario(
                $parcela,
                (int) ($filtros['anio'] ?? now()->year),
                $filtros['campania'] ?? 'todas'
            )
        );
    }

    private function validarSinCultivosActivos(Parcela $parcela): void
    {
        if ($parcela->cultivoVariedades()->where('estado', 'Activo')->exists()) {
            throw new ReglaNegocioException('No se puede inactivar la parcela porque tiene cultivos activos registrados.');
        }
    }
}
