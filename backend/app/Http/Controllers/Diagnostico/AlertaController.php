<?php

namespace App\Http\Controllers\Diagnostico;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Services\Alerta\AlertaSeguimientoService;
use App\Support\Paginacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    private const NIVELES_CRITICOS = ['Critico', 'Crítico'];

    private const ESTADOS_CERRADOS = ['Resuelta', 'Descartada'];

    private const ESTADOS_SEGUIMIENTO = [
        'Nueva',
        'En_revision',
        'Planificada',
        'En_tratamiento',
        'En_seguimiento',
        'Escalada',
        'Reincidente',
        'Resuelta',
        'Descartada',
    ];

    public function __construct(
        private readonly AlertaSeguimientoService $seguimientoService
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'ver_alertas');

        $alertas = Alerta::with([
            'diagnostico:id,id_inspeccion,id_enfermedad,porcentaje_confianza,nivel_severidad,estado_validacion',
            'diagnostico.inspeccion:id,id_parcela,fecha_visita,estado,pct_plantas_afectadas',
            'diagnostico.inspeccion.parcela:id,codigo_parcela,nombre_referencial,id_productor',
            'diagnostico.inspeccion.parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno',
            'diagnostico.enfermedad:id,nombre_comun,nivel_riesgo_base,medidas_control',
            'usuarioDestino:id,name,paterno,materno',
            'responsable:id,name,paterno,materno',
        ])
            ->when($request->nivel_riesgo, function ($query, $nivel) {
                in_array($nivel, self::NIVELES_CRITICOS, true)
                    ? $query->whereIn('nivel_riesgo', self::NIVELES_CRITICOS)
                    : $query->where('nivel_riesgo', $nivel);
            })
            ->when($request->estado, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($request->estado_seguimiento, fn ($query, $estado) => $query->where('estado_seguimiento', $estado))
            ->when($request->boolean('solo_mias'), fn ($query) => $query->where('id_usuario_destino', $request->user()->id))
            ->when($request->boolean('solo_asignadas'), fn ($query) => $query->where('id_responsable', $request->user()->id))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate(Paginacion::porPagina($request->query('per_page')));

        return response()->json($alertas);
    }

    public function show(Request $request, Alerta $alerta)
    {
        $this->checkPermiso($request, 'ver_alertas');

        $alerta->load([
            'diagnostico:id,id_inspeccion,id_enfermedad,porcentaje_confianza,nivel_severidad,estado_validacion,recomendacion',
            'diagnostico.inspeccion:id,id_parcela,fecha_visita,estado,observaciones,pct_plantas_afectadas',
            'diagnostico.inspeccion.parcela:id,codigo_parcela,nombre_referencial,id_productor',
            'diagnostico.inspeccion.parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad',
            'diagnostico.enfermedad',
            'usuarioDestino:id,name,paterno,materno',
            'responsable:id,name,paterno,materno',
        ]);

        // Informa al frontend qué transiciones permite el estado actual.
        $alerta->setAttribute('estados_disponibles', $this->seguimientoService->estadosDisponibles($alerta));

        return response()->json($alerta);
    }

    public function marcarLeida(Request $request, Alerta $alerta)
    {
        $this->checkPermiso($request, 'ver_alertas');

        if ($alerta->leida) {
            return response()->json([
                'message' => 'La alerta ya estaba marcada como leída.',
                'alerta' => $alerta,
            ]);
        }

        $alerta->update([
            'leida' => true,
            'fecha_lectura' => now(),
        ]);

        return response()->json([
            'message' => 'Alerta marcada como leída.',
            'alerta' => $this->cargarAlerta($alerta),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Flujo de seguimiento fitosanitario
    |--------------------------------------------------------------------------
    | Nueva → En revisión → Planificada → En tratamiento → En seguimiento → Resuelta
    */

    public function atender(Request $request, Alerta $alerta)
    {
        $this->checkPermiso($request, 'gestionar_alertas');

        $data = $request->validate([
            'accion_tomada' => ['nullable', 'string', 'max:1500'],
        ]);

        $alerta = $this->seguimientoService->cambiarEstado(
            $alerta,
            'En_revision',
            array_filter($data)
        );

        if (! $alerta->leida) {
            $alerta->update([
                'leida' => true,
                'fecha_lectura' => now(),
            ]);
        }

        return $this->respuesta('La alerta pasó a revisión.', $alerta);
    }

    public function planificar(Request $request, Alerta $alerta)
    {
        $this->checkPermiso($request, 'gestionar_alertas');

        $data = $request->validate([
            'id_responsable' => ['required', 'integer', Rule::exists('users', 'id')->where('activo', true)],
            'plan_manejo' => ['required', 'string', 'min:10', 'max:3000'],
            'proxima_revision' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'id_responsable.required' => 'Debe asignar un responsable.',
            'id_responsable.exists' => 'El responsable seleccionado no existe o está inactivo.',
            'plan_manejo.required' => 'Debe registrar un plan de manejo.',
            'plan_manejo.min' => 'El plan de manejo debe contener al menos 10 caracteres.',
            'proxima_revision.required' => 'Debe programar la próxima revisión.',
            'proxima_revision.after_or_equal' => 'La próxima revisión no puede ser una fecha anterior a hoy.',
        ]);

        $alerta = $this->seguimientoService->cambiarEstado($alerta, 'Planificada', $data);

        return $this->respuesta('Plan de manejo registrado correctamente.', $alerta);
    }

    public function iniciarTratamiento(Request $request, Alerta $alerta)
    {
        $this->checkPermiso($request, 'gestionar_alertas');

        $data = $request->validate([
            'accion_tomada' => ['nullable', 'string', 'max:1500'],
            'proxima_revision' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'proxima_revision.after_or_equal' => 'La próxima revisión no puede ser una fecha anterior a hoy.',
        ]);

        $alerta = $this->seguimientoService->cambiarEstado($alerta, 'En_tratamiento', $data);

        return $this->respuesta('Tratamiento iniciado correctamente.', $alerta);
    }

    public function registrarSeguimiento(Request $request, Alerta $alerta)
    {
        $this->checkPermiso($request, 'gestionar_alertas');

        $data = $request->validate([
            'accion_tomada' => ['required', 'string', 'min:5', 'max:1500'],
            'resultado_seguimiento' => ['required', 'in:Mejorando,Sin_cambios,Empeorando,Controlado'],
            'progreso_seguimiento' => ['required', 'integer', 'between:0,99'],
            'proxima_revision' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'accion_tomada.required' => 'Debe registrar la acción realizada.',
            'accion_tomada.min' => 'La acción realizada debe contener al menos 5 caracteres.',
            'resultado_seguimiento.required' => 'Debe registrar el resultado de la revisión.',
            'resultado_seguimiento.in' => 'El resultado del seguimiento no es válido.',
            'progreso_seguimiento.required' => 'Debe registrar el progreso del seguimiento.',
            'progreso_seguimiento.between' => 'El progreso debe encontrarse entre 0 y 99%.',
            'proxima_revision.required' => 'Debe programar una nueva revisión.',
            'proxima_revision.after_or_equal' => 'La próxima revisión no puede ser una fecha anterior a hoy.',
        ]);

        $alerta = $this->seguimientoService->cambiarEstado($alerta, 'En_seguimiento', $data);

        return $this->respuesta('Seguimiento registrado correctamente.', $alerta);
    }

    public function resolver(Request $request, Alerta $alerta)
    {
        $this->checkPermiso($request, 'gestionar_alertas');

        $data = $request->validate([
            'resultado_seguimiento' => ['required', 'in:Mejorando,Sin_cambios,Empeorando,Controlado'],
            'observacion_cierre' => ['required', 'string', 'min:10', 'max:2000'],
            'accion_tomada' => ['nullable', 'string', 'max:1500'],
        ], [
            'resultado_seguimiento.required' => 'Debe registrar el resultado final.',
            'resultado_seguimiento.in' => 'El resultado final no es válido.',
            'observacion_cierre.required' => 'Debe registrar una observación de cierre.',
            'observacion_cierre.min' => 'La observación de cierre debe contener al menos 10 caracteres.',
        ]);

        $alerta = $this->seguimientoService->cambiarEstado($alerta, 'Resuelta', $data);

        return $this->respuesta('Alerta resuelta correctamente.', $alerta);
    }

    /*
    |--------------------------------------------------------------------------
    | Indicadores
    |--------------------------------------------------------------------------
    */

    public function estadisticas(Request $request)
    {
        $this->checkPermiso($request, 'ver_alertas');

        $porSeguimiento = array_fill_keys(self::ESTADOS_SEGUIMIENTO, 0);

        foreach (Alerta::selectRaw('estado_seguimiento, COUNT(*) as total')->groupBy('estado_seguimiento')->get() as $fila) {
            $porSeguimiento[$fila->estado_seguimiento] = (int) $fila->total;
        }

        // Pendientes: todas las que todavía requieren alguna acción.
        $pendientes = fn () => Alerta::whereNotIn('estado_seguimiento', self::ESTADOS_CERRADOS);

        $porPrioridad = ['Critico' => 0, 'Alto' => 0, 'Medio' => 0, 'Bajo' => 0];

        foreach ($pendientes()->selectRaw('nivel_riesgo, COUNT(*) as total')->groupBy('nivel_riesgo')->get() as $fila) {
            $nivel = in_array($fila->nivel_riesgo, self::NIVELES_CRITICOS, true) ? 'Critico' : $fila->nivel_riesgo;
            $porPrioridad[$nivel] = ($porPrioridad[$nivel] ?? 0) + (int) $fila->total;
        }

        $total = array_sum($porSeguimiento);
        $resueltas = $porSeguimiento['Resuelta'];
        $descartadas = $porSeguimiento['Descartada'];
        $baseResolucion = max(0, $total - $descartadas);

        return response()->json([
            'total' => $total,
            'pendientes' => array_sum($porPrioridad),
            'criticas' => $porPrioridad['Critico'],
            'vencidas_seguimiento' => $pendientes()->whereNotNull('proxima_revision')->where('proxima_revision', '<', now())->count(),
            'sin_responsable' => $pendientes()->whereNull('id_responsable')->count(),
            'en_tratamiento' => $porSeguimiento['En_tratamiento'],
            'en_seguimiento' => $porSeguimiento['En_seguimiento'],
            'resueltas' => $resueltas,
            'descartadas' => $descartadas,
            'no_leidas' => Alerta::where('leida', false)->where('estado', 'Activa')->count(),
            'tasa_resolucion' => $baseResolucion > 0 ? round(($resueltas / $baseResolucion) * 100, 2) : 0,
            'por_prioridad' => $porPrioridad,
            'por_seguimiento' => $porSeguimiento,
        ]);
    }

    public function noLeidas(Request $request)
    {
        $this->checkPermiso($request, 'ver_alertas');

        $count = Alerta::where('id_usuario_destino', $request->user()->id)
            ->where('leida', false)
            ->where('estado', 'Activa')
            ->count();

        return response()->json(['count' => $count]);
    }

    private function respuesta(string $mensaje, Alerta $alerta)
    {
        return response()->json([
            'message' => $mensaje,
            'alerta' => $this->cargarAlerta($alerta),
        ]);
    }

    private function cargarAlerta(Alerta $alerta): Alerta
    {
        $alerta = $alerta->fresh([
            'diagnostico.inspeccion.parcela.productor',
            'diagnostico.enfermedad',
            'usuarioDestino:id,name,paterno,materno',
            'responsable:id,name,paterno,materno',
        ]);

        $alerta->setAttribute('estados_disponibles', $this->seguimientoService->estadosDisponibles($alerta));

        return $alerta;
    }
}
