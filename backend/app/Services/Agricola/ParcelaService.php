<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\ParcelaDTO;
use App\Models\Parcela;
use App\Models\User;

class ParcelaService
{
    // Ver parcela con todo el detalle
    public function verDetalle(Parcela $parcela): Parcela
    {
        return $parcela->load([
            'productor:id,nombre_completo,carnet_identidad',
            'cultivoVariedades.etapaFenologica:id,nombre,codigo',
            'inspecciones' => fn ($q) => $q->latest()->limit(5),
            'cosechas' => fn ($q) => $q->latest()->limit(5),
        ]);
    }

    // Crear parcela
    public function crear(ParcelaDTO $dto): Parcela
    {
        return Parcela::create($dto->toArray());
    }

    // Actualizar parcela
    public function actualizar(Parcela $parcela, ParcelaDTO $dto): Parcela
    {
        $parcela->update($dto->toArray());

        return $parcela->fresh();
    }

    // Cambiar estado
    public function cambiarEstado(Parcela $parcela, string $estado): Parcela
    {
        $parcela->update(['estado' => $estado]);

        return $parcela;
    }

    // Historial fitosanitario de una parcela por gestión y campaña
    public function historialFitosanitario(Parcela $parcela, int $anio, string $campania): array
    {
        $parcela->load('productor');

        $mesInicio = null;
        $mesFin = null;

        if ($campania === 'primera') {
            $mesInicio = 1;
            $mesFin = 6;
        }

        if ($campania === 'segunda') {
            $mesInicio = 7;
            $mesFin = 12;
        }

        $cultivos = $parcela->cultivoVariedades()
            ->with([
                'etapaFenologica',
            ])
            ->whereYear('fecha_siembra', $anio)
            ->when($campania !== 'todas', function ($query) use ($mesInicio, $mesFin) {
                $query->whereMonth('fecha_siembra', '>=', $mesInicio)
                    ->whereMonth('fecha_siembra', '<=', $mesFin);
            })
            ->orderByDesc('fecha_siembra')
            ->orderByDesc('id')
            ->get();

        $inspecciones = $parcela->inspecciones()
            ->with([
                'usuario:id,name,paterno,materno,email',
                'programadoPor:id,name,paterno,materno,email',
                'validadoPor:id,name,paterno,materno,email',
                'cultivo',
                'etapaFenologica',
                'diagnosticos.enfermedad',
                'diagnosticos.validadoPor:id,name,paterno,materno,email',
                'diagnosticos.alertas.usuarioDestino:id,name,paterno,materno,email',
            ])
            ->whereYear('fecha_visita', $anio)
            ->when($campania !== 'todas', function ($query) use ($mesInicio, $mesFin) {
                $query->whereMonth('fecha_visita', '>=', $mesInicio)
                    ->whereMonth('fecha_visita', '<=', $mesFin);
            })
            ->orderByDesc('fecha_visita')
            ->orderByDesc('id')
            ->get();

        $cosechas = $parcela->cosechas()
            ->with([
                'cultivo',
                'usuario:id,name,paterno,materno,email',
            ])
            ->whereYear('fecha_cosecha', $anio)
            ->when($campania !== 'todas', function ($query) use ($mesInicio, $mesFin) {
                $query->whereMonth('fecha_cosecha', '>=', $mesInicio)
                    ->whereMonth('fecha_cosecha', '<=', $mesFin);
            })
            ->orderByDesc('fecha_cosecha')
            ->orderByDesc('id')
            ->get();

        $diagnosticos = collect();
        $alertas = collect();

        foreach ($inspecciones as $inspeccion) {
            foreach ($inspeccion->diagnosticos as $diagnostico) {
                $diagnosticos->push([
                    'id' => $diagnostico->id,
                    'id_inspeccion' => $diagnostico->id_inspeccion,
                    'fecha_inspeccion' => $inspeccion->fecha_visita,

                    'ruta_fotografia' => $diagnostico->ruta_fotografia,
                    'nombre_archivo' => $diagnostico->nombre_archivo,

                    'enfermedad' => $diagnostico->enfermedad ? [
                        'id' => $diagnostico->enfermedad->id,
                        'nombre' => $diagnostico->enfermedad->nombre_comun ?? 'Sin nombre',
                        'nombre_comun' => $diagnostico->enfermedad->nombre_comun ?? null,
                        'nombre_cientifico' => $diagnostico->enfermedad->nombre_cientifico ?? null,
                        'nivel_riesgo_base' => $diagnostico->enfermedad->nivel_riesgo_base ?? null,
                        'medidas_control' => $diagnostico->enfermedad->medidas_control ?? null,
                    ] : null,

                    'porcentaje_confianza' => $diagnostico->porcentaje_confianza,
                    'top3_predicciones' => $diagnostico->top3_predicciones,
                    'modelo_version' => $diagnostico->modelo_version,
                    'nivel_severidad' => $diagnostico->nivel_severidad,
                    'recomendacion' => $diagnostico->recomendacion,

                    'estado_validacion' => $diagnostico->estado_validacion,
                    'observaciones_validacion' => $diagnostico->observaciones_validacion,
                    'fecha_validacion' => $diagnostico->fecha_validacion,

                    'validado_por' => $diagnostico->validadoPor ? [
                        'id' => $diagnostico->validadoPor->id,
                        'nombre' => $this->nombreUsuario($diagnostico->validadoPor),
                        'email' => $diagnostico->validadoPor->email,
                    ] : null,
                ]);

                foreach ($diagnostico->alertas as $alerta) {
                    $alertas->push([
                        'id' => $alerta->id,
                        'id_diagnostico' => $alerta->id_diagnostico,
                        'nivel_riesgo' => $alerta->nivel_riesgo,
                        'titulo' => $alerta->titulo,
                        'mensaje' => $alerta->mensaje,
                        'accion_recomendada' => $alerta->accion_recomendada,
                        'fecha_emision' => $alerta->fecha_emision,
                        'fecha_limite_accion' => $alerta->fecha_limite_accion,
                        'leida' => $alerta->leida,
                        'fecha_lectura' => $alerta->fecha_lectura,
                        'estado' => $alerta->estado,
                        'accion_tomada' => $alerta->accion_tomada,

                        'usuario_destino' => $alerta->usuarioDestino ? [
                            'id' => $alerta->usuarioDestino->id,
                            'nombre' => $this->nombreUsuario($alerta->usuarioDestino),
                            'email' => $alerta->usuarioDestino->email,
                        ] : null,
                    ]);
                }
            }
        }

        return [

            'filtros' => [
                'anio' => $anio,
                'campania' => $campania,
                'nombre_gestion' => 'Gestión agrícola '.$anio,
                'nombre_campania' => match ($campania) {
                    'primera' => 'Primera campaña',
                    'segunda' => 'Segunda campaña',
                    default => 'Todas las campañas',
                },
            ],
            'parcela' => [
                'id' => $parcela->id,
                'codigo_parcela' => $parcela->codigo_parcela,
                'nombre_referencial' => $parcela->nombre_referencial,
                'latitud' => $parcela->latitud,
                'longitud' => $parcela->longitud,
                'coordenadas_texto' => $parcela->coordenadas_texto,
                'altitud_msnm' => $parcela->altitud_msnm,
                'superficie_ha' => $parcela->superficie_ha,
                'tipo_suelo' => $parcela->tipo_suelo,
                'pendiente' => $parcela->pendiente,
                'sistema_riego' => $parcela->sistema_riego,
                'observaciones' => $parcela->observaciones,
                'estado' => $parcela->estado,

                'productor' => $parcela->productor ? [
                    'id' => $parcela->productor->id,
                    'nombre_completo' => $parcela->productor->nombre_completo
                        ?? trim(
                            ($parcela->productor->nombres ?? '').' '.
                                ($parcela->productor->apellido_paterno ?? '').' '.
                                ($parcela->productor->apellido_materno ?? '')
                        ),
                    'carnet_identidad' => $parcela->productor->carnet_identidad ?? null,
                ] : null,
            ],

            'resumen' => [
                'total_cultivos' => $cultivos->count(),
                'cultivos_activos' => $cultivos->where('estado', 'Activo')->count(),

                'total_inspecciones' => $inspecciones->count(),
                'inspecciones_pendientes' => $inspecciones->where('estado', 'Pendiente')->count(),
                'inspecciones_en_proceso' => $inspecciones->where('estado', 'En_proceso')->count(),
                'inspecciones_completadas' => $inspecciones->where('estado', 'Completada')->count(),
                'inspecciones_validadas' => $inspecciones->where('estado', 'Validada')->count(),
                'inspecciones_no_realizadas' => $inspecciones->where('estado', 'No_realizada')->count(),

                'total_diagnosticos' => $diagnosticos->count(),
                'diagnosticos_pendientes' => $diagnosticos->where('estado_validacion', 'Pendiente')->count(),
                'diagnosticos_confirmados' => $diagnosticos->where('estado_validacion', 'Confirmado')->count(),
                'diagnosticos_corregidos' => $diagnosticos->where('estado_validacion', 'Corregido')->count(),
                'diagnosticos_rechazados' => $diagnosticos->where('estado_validacion', 'Rechazado')->count(),

                'total_alertas' => $alertas->count(),
                'alertas_pendientes' => $alertas->where('estado', 'Activa')->count(),
                'alertas_atendidas' => $alertas->where('estado', 'Atendida')->count(),

                'total_cosechas' => $cosechas->count(),
            ],

            'cultivos' => $cultivos->map(function ($cultivo) {
                return [
                    'id' => $cultivo->id,
                    'variedad_papa' => $cultivo->variedad_papa,
                    'fecha_siembra' => $cultivo->fecha_siembra,
                    'cantidad_semilla_kg' => $cultivo->cantidad_semilla_kg,
                    'procedencia_semilla' => $cultivo->procedencia_semilla,
                    'fecha_estimada_cosecha' => $cultivo->fecha_estimada_cosecha,
                    'observaciones' => $cultivo->observaciones,
                    'estado' => $cultivo->estado,
                    'etapa_fenologica' => $cultivo->etapaFenologica,
                ];
            })->values(),

            'inspecciones' => $inspecciones->map(function ($inspeccion) {
                return [
                    'id' => $inspeccion->id,

                    'tipo_inspeccion' => $inspeccion->tipo_inspeccion ?? 'Cultivo',

                    'fecha_visita' => $inspeccion->fecha_visita,
                    'hora_visita' => $inspeccion->hora_visita,
                    'fecha_inicio' => $inspeccion->fecha_inicio,
                    'fecha_finalizacion' => $inspeccion->fecha_finalizacion,
                    'fecha_validacion' => $inspeccion->fecha_validacion,

                    'condicion_climatica' => $inspeccion->condicion_climatica,
                    'temperatura_c' => $inspeccion->temperatura_c,
                    'humedad_relativa_pct' => $inspeccion->humedad_relativa_pct,
                    'pct_plantas_afectadas' => $inspeccion->pct_plantas_afectadas,
                    'observaciones' => $inspeccion->observaciones,
                    'estado' => $inspeccion->estado,

                    'motivo_no_realizada' => $inspeccion->motivo_no_realizada,
                    'fecha_reprogramada' => $inspeccion->fecha_reprogramada,
                    'recomendacion_reprogramacion' => $inspeccion->recomendacion_reprogramacion,

                    'condicion_terreno' => $inspeccion->condicion_terreno,
                    'recomendacion_pre_siembra' => $inspeccion->recomendacion_pre_siembra,

                    'cultivo' => $inspeccion->cultivo,
                    'etapa_fenologica' => $inspeccion->etapaFenologica,

                    'usuario' => $inspeccion->usuario ? [
                        'id' => $inspeccion->usuario->id,
                        'nombre' => $this->nombreUsuario($inspeccion->usuario),
                        'email' => $inspeccion->usuario->email,
                    ] : null,

                    'programado_por' => $inspeccion->programadoPor ? [
                        'id' => $inspeccion->programadoPor->id,
                        'nombre' => $this->nombreUsuario($inspeccion->programadoPor),
                        'email' => $inspeccion->programadoPor->email,
                    ] : null,

                    'validado_por' => $inspeccion->validadoPor ? [
                        'id' => $inspeccion->validadoPor->id,
                        'nombre' => $this->nombreUsuario($inspeccion->validadoPor),
                        'email' => $inspeccion->validadoPor->email,
                    ] : null,

                    'total_diagnosticos' => $inspeccion->diagnosticos->count(),
                ];
            })->values(),

            'diagnosticos' => $diagnosticos->values(),

            'alertas' => $alertas->values(),

            'cosechas' => $cosechas->map(function ($cosecha) {
                return [
                    'id' => $cosecha->id,
                    'fecha_cosecha' => $cosecha->fecha_cosecha,
                    'cantidad_toneladas' => $cosecha->cantidad_toneladas,
                    'rendimiento_t_ha' => $cosecha->rendimiento_t_ha,
                    'calidad' => $cosecha->calidad,
                    'destino' => $cosecha->destino,
                    'precio_unitario_bs' => $cosecha->precio_unitario_bs,
                    'ingreso_estimado_bs' => $cosecha->ingreso_estimado_bs,
                    'observaciones' => $cosecha->observaciones,
                    'cultivo' => $cosecha->cultivo,

                    'usuario' => $cosecha->usuario ? [
                        'id' => $cosecha->usuario->id,
                        'nombre' => $this->nombreUsuario($cosecha->usuario),
                        'email' => $cosecha->usuario->email,
                    ] : null,
                ];
            })->values(),
        ];
    }

    private function nombreUsuario(?User $user): string
    {
        if (! $user) {
            return '—';
        }

        return trim(
            ($user->name ?? '').' '.
                ($user->paterno ?? '').' '.
                ($user->materno ?? '')
        ) ?: '—';
    }
}
