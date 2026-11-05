<?php

namespace App\Http\Controllers\Diagnostico;

use App\DTOs\Diagnostico\DiagnosticoDTO;
use App\DTOs\Diagnostico\ValidarDiagnosticoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diagnostico\StoreDiagnosticoRequest;
use App\Http\Requests\Diagnostico\ValidarDiagnosticoRequest;
use App\Models\DiagnosticoCNN;
use App\Services\Diagnostico\DiagnosticoService;
use Illuminate\Http\Request;

class DiagnosticoController extends Controller
{
    public function __construct(
        private readonly DiagnosticoService $service
    ) {}

    public function estadoCnn(Request $request)
    {
        $this->checkPermiso($request, 'ver_diagnosticos');

        return response()->json($this->service->estadoModelo());
    }

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'ver_diagnosticos');

        $filtros = $request->validate([
            'id_inspeccion' => ['nullable', 'integer', 'exists:inspecciones,id'],
            'estado_validacion' => ['nullable', 'in:Pendiente,Confirmado,Corregido,Rechazado'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $diagnosticos = DiagnosticoCNN::with([
            'enfermedad:id,nombre_comun,nivel_riesgo_base,medidas_control',
            'inspeccion:id,fecha_visita,id_parcela,estado',
            'inspeccion.parcela:id,codigo_parcela,nombre_referencial,id_productor',
            'inspeccion.parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno',
            'validadoPor:id,name,paterno,materno',
        ])
            ->when($filtros['id_inspeccion'] ?? null, fn ($query, $id) => $query->where('id_inspeccion', $id))
            ->when($filtros['estado_validacion'] ?? null, fn ($query, $estado) => $query->where('estado_validacion', $estado))
            ->latest()
            ->paginate($filtros['per_page'] ?? 15);

        return response()->json($diagnosticos);
    }

    public function show(Request $request, DiagnosticoCNN $diagnostico)
    {
        $this->checkPermiso($request, 'ver_diagnosticos');

        return response()->json($diagnostico->load([
            'inspeccion:id,fecha_visita,id_parcela,estado,observaciones',
            'inspeccion.parcela:id,codigo_parcela,nombre_referencial,id_productor',
            'inspeccion.parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad',
            'enfermedad',
            'enfermedad.productosRecomendados.inventarios.almacen',
            'validadoPor:id,name,paterno,materno',
            'alertas',
        ]));
    }

    public function store(StoreDiagnosticoRequest $request)
    {
        $this->checkPermiso($request, 'crear_diagnosticos');

        $dto = DiagnosticoDTO::fromRequest(
            $request->validated(),
            $request->file('fotografia')
        );

        $diagnostico = $this->service->crear(
            $dto,
            $request->user()->id
        );

        return response()->json($diagnostico, 201);
    }

    public function validar(ValidarDiagnosticoRequest $request, DiagnosticoCNN $diagnostico)
    {
        $this->checkPermiso($request, 'validar_diagnosticos');

        $dto = ValidarDiagnosticoDTO::fromRequest(
            $request->validated()
        );

        $diagnostico = $this->service->validar(
            $diagnostico,
            $dto,
            $request->user()->id
        );

        return response()->json($diagnostico);
    }
}
