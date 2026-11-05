<?php

namespace App\Services\Diagnostico;

use App\DTOs\Diagnostico\DiagnosticoDTO;
use App\DTOs\Diagnostico\ValidarDiagnosticoDTO;
use App\Exceptions\ReglaNegocioException;
use App\Models\Alerta;
use App\Models\DiagnosticoCNN;
use App\Models\Enfermedad;
use App\Models\Inspeccion;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DiagnosticoService
{
    private const CODIGO_SANA = 'CNN-SANA';

    public function __construct(
        private readonly CnnInferenceService $cnnInference,
        private readonly AlertaPrioridadService $alertaPrioridad
    ) {}

    public function crear(DiagnosticoDTO $dto, int $userId): DiagnosticoCNN
    {
        // La inferencia se realiza antes de abrir la transacción para no mantener
        // bloqueos de base de datos durante una llamada HTTP al microservicio.
        $prediction = $this->cnnInference->predecir($dto->fotografia);

        $codigoEnfermedad = (string) $prediction['codigo_enfermedad'];

        $enfermedad = Enfermedad::query()
            ->where('codigo_enfermedad', $codigoEnfermedad)
            ->where('activo', true)
            ->first();

        if (! $enfermedad) {
            throw new HttpException(
                422,
                "La CNN predijo la clase '{$codigoEnfermedad}', pero no existe una enfermedad activa con ese código en el sistema."
            );
        }

        return DB::transaction(function () use ($dto, $prediction, $enfermedad) {
            $ruta = $dto->fotografia->store('diagnosticos', 'public');

            $data = [
                ...$dto->toArray(
                    $ruta,
                    $dto->fotografia->getClientOriginalName()
                ),
                'id_enfermedad' => $enfermedad->id,
                'porcentaje_confianza' => round((float) $prediction['confianza'], 2),
                'top3_predicciones' => $prediction['top3'] ?? null,
                'modelo_version' => mb_substr(
                    (string) ($prediction['modelo_version'] ?? 'PROINPA-CNN'),
                    0,
                    50
                ),
                // La primera versión de la CNN clasifica enfermedad, no severidad.
                // La severidad se registra durante la validación agronómica.
                'nivel_severidad' => null,
                'recomendacion' => $enfermedad->medidas_control,
                'estado_validacion' => 'Pendiente',
                'validado_por' => null,
                'observaciones_validacion' => null,
                'fecha_validacion' => null,
            ];

            $diagnostico = DiagnosticoCNN::create($data);

            $inspeccion = Inspeccion::where('id', $dto->id_inspeccion)
                ->lockForUpdate()
                ->first();

            if ($inspeccion) {
                $datosInspeccion = [
                    'estado' => 'Completada',
                ];

                if (! $inspeccion->fecha_finalizacion) {
                    $datosInspeccion['fecha_finalizacion'] = now();
                }

                $inspeccion->update($datosInspeccion);
            }

            return $diagnostico->load([
                'inspeccion:id,fecha_visita,id_parcela,estado',
                'inspeccion.parcela:id,codigo_parcela,nombre_referencial,id_productor',
                'inspeccion.parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno',
                'enfermedad:id,nombre_comun,nombre_cientifico,codigo_enfermedad,nivel_riesgo_base,medidas_control',
                'enfermedad.productosRecomendados.inventarios.almacen',
            ]);
        });
    }

    public function estadoModelo(): array
    {
        return $this->cnnInference->estado();
    }

    public function validar(
        DiagnosticoCNN $diagnostico,
        ValidarDiagnosticoDTO $dto,
        int $userId
    ): DiagnosticoCNN {
        return DB::transaction(function () use ($diagnostico, $dto, $userId) {
            $diagnostico = DiagnosticoCNN::whereKey($diagnostico->id)->lockForUpdate()->firstOrFail();

            // Un diagnóstico validado no se sobrescribe (ni se rechaza después de generar su alerta).
            if ($diagnostico->estado_validacion !== 'Pendiente') {
                throw new ReglaNegocioException('Este diagnóstico ya fue validado anteriormente.');
            }

            $diagnostico->update([
                ...$dto->toArray(
                    $diagnostico->id_enfermedad,
                    $diagnostico->nivel_severidad
                ),
                'validado_por' => $userId,
                'fecha_validacion' => now(),
            ]);

            /*
|--------------------------------------------------------------------------
| ACTUALIZAR DATOS AGRONÓMICOS DE LA INSPECCIÓN
|--------------------------------------------------------------------------
|
| El porcentaje de plantas afectadas pertenece a la inspección
| de campo y no al resultado de la CNN.
|
*/
            if ($dto->pct_plantas_afectadas !== null) {
                $inspeccionActual = Inspeccion::query()
                    ->where('id', $diagnostico->id_inspeccion)
                    ->lockForUpdate()
                    ->first();

                if ($inspeccionActual) {
                    $inspeccionActual->update(
                        $dto->toInspeccionArray()
                    );
                }
            }

            /*
|--------------------------------------------------------------------------
| RECARGAR DATOS
|--------------------------------------------------------------------------
|
| Es importante recargar la inspección antes de calcular la alerta,
| porque el motor utiliza pct_plantas_afectadas.
|
*/
            $diagnostico = $diagnostico->fresh([
                'inspeccion',
                'enfermedad.productosRecomendados.inventarios',
            ]);

            if (in_array($dto->estado_validacion, ['Confirmado', 'Corregido'], true)) {
                $this->generarAlertaSiEsNecesario($diagnostico, $userId);
            }

            $inspeccion = $diagnostico->inspeccion;

            if ($inspeccion) {
                $todosValidados = $inspeccion->diagnosticos()
                    ->where('estado_validacion', 'Pendiente')
                    ->doesntExist();

                if ($todosValidados) {
                    $inspeccion->update([
                        'estado' => 'Validada',
                        'id_validado_por' => $userId,
                        'fecha_validacion' => now(),
                    ]);
                }
            }

            return $diagnostico->fresh()->load([
                'inspeccion:id,fecha_visita,id_parcela,estado,id_validado_por,fecha_validacion,pct_plantas_afectadas',
                'inspeccion.parcela:id,codigo_parcela,nombre_referencial,id_productor',
                'inspeccion.parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno',
                'enfermedad',
                'validadoPor:id,name,paterno,materno',
                'alertas',
            ]);
        });
    }

    private function generarAlertaSiEsNecesario(
        DiagnosticoCNN $diagnostico,
        int $userId
    ): void {
        $diagnostico->loadMissing([
            'enfermedad.productosRecomendados.inventarios',
            'inspeccion',
        ]);

        $enfermedad = $diagnostico->enfermedad;

        // Una planta sana no genera alerta, aunque sume puntos por
        // afectación o por otros diagnósticos sanos en la parcela.
        if (! $enfermedad || $enfermedad->codigo_enfermedad === self::CODIGO_SANA) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | CALCULAR PRIORIDAD INTELIGENTE
    |--------------------------------------------------------------------------
    */
        $prioridad = $this->alertaPrioridad->calcular($diagnostico);

        /*
    |--------------------------------------------------------------------------
    | DECIDIR SI CORRESPONDE GENERAR ALERTA
    |--------------------------------------------------------------------------
    |
    | Se genera cuando:
    |
    | 1. La enfermedad ya es de riesgo Alto/Crítico
    |    O
    | 2. El motor inteligente alcanza 30 puntos o más.
    |
    | Esto permite detectar situaciones relevantes aunque el riesgo base
    | de la enfermedad sea Medio.
    |
    */

        $riesgoBaseRelevante = in_array(
            $enfermedad->nivel_riesgo_base,
            ['Alto', 'Critico', 'Crítico'],
            true
        );

        $prioridadRelevante = $prioridad['puntaje'] >= 30;

        if (! $riesgoBaseRelevante && ! $prioridadRelevante) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | PRODUCTOS / INSUMOS RECOMENDADOS
    |--------------------------------------------------------------------------
    */
        $productos = $enfermedad->productosRecomendados
            ->map(function ($producto) {
                $stockTotal = $producto->inventarios->sum('stock_actual');

                return "- {$producto->nombre_producto} "
                    ."({$producto->categoria}) | "
                    ."Stock disponible: {$stockTotal} "
                    ."{$producto->unidad_medida}";
            })
            ->implode("\n");

        $accionRecomendada = trim(
            ($enfermedad->medidas_control ?? '')
                ."\n\nInsumos sugeridos:\n"
                .(
                    $productos
                    ?: 'No existen insumos vinculados a esta enfermedad.'
                )
        );

        /*
    |--------------------------------------------------------------------------
    | DATOS PARA EL MENSAJE
    |--------------------------------------------------------------------------
    */
        $severidad = $diagnostico->nivel_severidad
            ? str_replace('_', ' ', $diagnostico->nivel_severidad)
            : 'No registrada';

        /*
    |--------------------------------------------------------------------------
    | FECHA LÍMITE SEGÚN PRIORIDAD
    |--------------------------------------------------------------------------
    */

        $diasParaActuar = match ($prioridad['nivel']) {
            'Critico' => 1,
            'Alto' => 2,
            'Medio' => 3,
            default => 5,
        };

        /*
    |--------------------------------------------------------------------------
    | CREAR O ACTUALIZAR ALERTA
    |--------------------------------------------------------------------------
    */

        Alerta::updateOrCreate(
            [
                'id_diagnostico' => $diagnostico->id,
            ],
            [
                'id_usuario_destino' => $userId,

                // Prioridad calculada
                'nivel_riesgo' => $prioridad['nivel'],
                'puntaje_prioridad' => $prioridad['puntaje'],
                'motivos_prioridad' => $prioridad['motivos'],
                'prioridad_automatica' => true,
                'prioridad_calculada_at' => now(),

                'titulo' => "⚠️ {$enfermedad->nombre_comun} — "
                    ."{$prioridad['nivel']}",

                'mensaje' => "Se confirmó {$enfermedad->nombre_comun}. "
                    ."Prioridad calculada: {$prioridad['puntaje']}/100. "
                    ."Severidad observada: {$severidad}. "
                    ."Confianza CNN: {$diagnostico->porcentaje_confianza}%.",

                'accion_recomendada' => $accionRecomendada,

                'fecha_emision' => now()->toDateString(),

                'fecha_limite_accion' => now()
                    ->addDays($diasParaActuar)
                    ->toDateString(),

                'estado' => 'Activa',
            ]
        );
    }
}
