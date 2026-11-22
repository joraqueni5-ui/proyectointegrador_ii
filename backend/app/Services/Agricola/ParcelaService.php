<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\ParcelaDTO;
use App\Models\Parcela;
use App\Models\User;
use App\Support\Campana;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ParcelaService
{
    // Ver parcela con todo el detalle
    public function verDetalle(Parcela $parcela): Parcela
    {
        $parcela->load([
            'productor:id,id_comunidad,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad,telefono',
            'productor.comunidad:id,nombre_comunidad,municipio,latitud,longitud',
            'cultivoVariedades.etapaFenologica:id,nombre,codigo,orden',
            'inspecciones' => fn ($q) => $q->latest()->limit(5),
            'cosechas' => fn ($q) => $q->latest()->limit(5),
        ]);

        // Resumen sanitario (foto, alerta, última visita) de esta parcela, con la misma consulta del listado.
        $resumen = Parcela::query()->conResumenSanitario()->whereKey($parcela->id)->first();

        foreach (['ultima_foto', 'ultimo_diagnostico_estado', 'ultimo_diagnostico_confianza', 'ultimo_diagnostico_fecha', 'ultimo_diagnostico_nombre',
            'ultimo_diagnostico_codigo', 'ultima_visita', 'alertas_activas', 'alerta_puntaje', 'alerta_enfermedad'] as $campo) {
            $parcela->setAttribute($campo, $resumen?->getAttribute($campo));
        }

        $parcela->setAttribute('produccion_por_campana', $this->produccionPorCampana($parcela));

        return $parcela;
    }

    /** Toneladas y t/ha por campaña agrícola (la campaña sale de App\Support\Campana). */
    public function produccionPorCampana(Parcela $parcela): array
    {
        $ha = (float) $parcela->superficie_ha;

        return $parcela->cosechas()
            ->get(['fecha_cosecha', 'cantidad_toneladas'])
            ->groupBy(fn ($c) => Campana::etiquetaDe($c->fecha_cosecha))
            ->map(fn ($grupo, $campana) => [
                'campana' => $campana,
                'toneladas' => round((float) $grupo->sum('cantidad_toneladas'), 3),
                'rendimiento_t_ha' => $ha > 0 ? round((float) $grupo->sum('cantidad_toneladas') / $ha, 2) : null,
                'cosechas' => $grupo->count(),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /**
     * Línea de tiempo sanitaria de una parcela: siembras, inspecciones, diagnósticos, alertas y
     * cosechas en orden cronológico inverso. Cinco consultas fijas (sin N+1).
     *
     * @param  array<int,string>|null  $tipos  siembra|inspeccion|diagnostico|alerta|cosecha
     */
    public function lineaTiempo(Parcela $parcela, int $limite = 60, ?array $tipos = null, ?string $desde = null, ?string $hasta = null): array
    {
        $quiere = fn (string $tipo) => $tipos === null || in_array($tipo, $tipos, true);
        $rango = function ($consulta, string $columna) use ($desde, $hasta) {
            return $consulta->when($desde, fn ($q) => $q->whereDate($columna, '>=', $desde))
                ->when($hasta, fn ($q) => $q->whereDate($columna, '<=', $hasta));
        };

        $eventos = new Collection;

        if ($quiere('siembra')) {
            $rango($parcela->cultivoVariedades(), 'fecha_siembra')->get()->each(fn ($c) => $eventos->push([
                'id' => "siembra-{$c->id}", 'tipo' => 'siembra', 'fecha' => $c->fecha_siembra?->toDateString(),
                'titulo' => 'Siembra de '.$c->variedad_papa,
                'detalle' => $c->cantidad_semilla_kg ? 'Semilla: '.rtrim(rtrim(number_format((float) $c->cantidad_semilla_kg, 2, ',', '.'), '0'), ',').' kg' : null,
                'estado' => $c->estado,
            ]));
        }

        if ($quiere('inspeccion')) {
            $rango($parcela->inspecciones()->with('usuario:id,name,paterno'), 'fecha_visita')->get()->each(fn ($i) => $eventos->push([
                'id' => "inspeccion-{$i->id}", 'tipo' => 'inspeccion', 'fecha' => $i->fecha_visita?->toDateString(),
                'titulo' => 'Inspección '.($i->tipo_inspeccion === 'Cultivo' ? 'de rutina' : strtolower((string) $i->tipo_inspeccion)),
                'detalle' => trim(($i->usuario ? 'Técnico: '.trim($i->usuario->name.' '.$i->usuario->paterno) : '')
                    .($i->pct_plantas_afectadas !== null ? ' · '.rtrim(rtrim(number_format((float) $i->pct_plantas_afectadas, 1, ',', '.'), '0'), ',').' % de plantas afectadas' : ''), ' ·') ?: null,
                'estado' => $i->estado,
            ]));
        }

        if ($quiere('diagnostico') || $quiere('alerta')) {
            $diagnosticos = DB::table('diagnosticos_cnn as d')
                ->join('inspecciones as i', 'i.id', '=', 'd.id_inspeccion')
                ->leftJoin('enfermedades as e', 'e.id', '=', 'd.id_enfermedad')
                ->where('i.id_parcela', $parcela->id)
                ->when($desde, fn ($q) => $q->whereDate('d.created_at', '>=', $desde))
                ->when($hasta, fn ($q) => $q->whereDate('d.created_at', '<=', $hasta))
                ->orderByDesc('d.created_at')
                ->limit($limite)
                ->get(['d.id', 'd.created_at', 'd.ruta_fotografia', 'd.porcentaje_confianza', 'd.estado_validacion', 'd.nivel_severidad', 'e.nombre_comun', 'e.codigo_enfermedad']);

            if ($quiere('diagnostico')) {
                $diagnosticos->each(fn ($d) => $eventos->push([
                    'id' => "diagnostico-{$d->id}", 'tipo' => 'diagnostico', 'fecha' => substr((string) $d->created_at, 0, 10),
                    'titulo' => 'Diagnóstico: '.($d->nombre_comun ?? 'sin resultado'),
                    'detalle' => $d->porcentaje_confianza !== null ? 'Confianza del modelo: '.rtrim(rtrim(number_format((float) $d->porcentaje_confianza, 1, ',', '.'), '0'), ',').' %' : null,
                    'estado' => $d->estado_validacion, 'foto' => $d->ruta_fotografia, 'severidad' => $d->nivel_severidad,
                ]));
            }
        }

        if ($quiere('alerta')) {
            $rango(DB::table('alertas as a')
                ->join('diagnosticos_cnn as d', 'd.id', '=', 'a.id_diagnostico')
                ->join('inspecciones as i', 'i.id', '=', 'd.id_inspeccion')
                ->where('i.id_parcela', $parcela->id)
                ->orderByDesc('a.fecha_emision')
                ->limit($limite), 'a.fecha_emision')
                ->get(['a.id', 'a.fecha_emision', 'a.titulo', 'a.nivel_riesgo', 'a.estado', 'a.estado_seguimiento', 'a.puntaje_prioridad'])
                ->each(fn ($a) => $eventos->push([
                    'id' => "alerta-{$a->id}", 'tipo' => 'alerta', 'fecha' => substr((string) $a->fecha_emision, 0, 10),
                    'titulo' => 'Alerta: '.$a->titulo,
                    'detalle' => 'Riesgo '.strtolower((string) $a->nivel_riesgo).($a->puntaje_prioridad !== null ? ' · prioridad '.$a->puntaje_prioridad : ''),
                    'estado' => $a->estado === 'Activa' ? 'Activa' : $a->estado, 'puntaje' => $a->puntaje_prioridad, 'seguimiento' => $a->estado_seguimiento,
                ]));
        }

        if ($quiere('cosecha')) {
            $rango($parcela->cosechas(), 'fecha_cosecha')->get()->each(fn ($c) => $eventos->push([
                'id' => "cosecha-{$c->id}", 'tipo' => 'cosecha', 'fecha' => $c->fecha_cosecha?->toDateString(),
                'titulo' => 'Cosecha de '.rtrim(rtrim(number_format((float) $c->cantidad_toneladas, 3, ',', '.'), '0'), ',').' t',
                'detalle' => $c->calidad ? 'Calidad: '.$c->calidad : null, 'estado' => null,
            ]));
        }

        return $eventos
            ->filter(fn ($e) => ! empty($e['fecha']))
            ->sortByDesc(fn ($e) => $e['fecha'].'|'.str_pad((string) preg_replace('/\D/', '', $e['id']), 10, '0', STR_PAD_LEFT))
            ->take($limite)
            ->values()
            ->all();
    }

    /**
     * Código automático para parcelas NUEVAS: 3 primeras letras de la comunidad (sin tildes, en
     * mayúsculas), iniciales del productor y correlativo de 2 dígitos. Ej.: CHI-HYC-03.
     * Si el código ya existe se sugiere el siguiente libre.
     */
    public function siguienteCodigo(int $idProductor): array
    {
        $productor = \App\Models\Productor::with('comunidad:id,nombre_comunidad')->findOrFail($idProductor);

        $sinTildes = fn (string $t) => strtoupper(preg_replace('/[^A-Za-z]/', '', \Illuminate\Support\Str::ascii($t)));

        $comunidad = substr($sinTildes((string) $productor->comunidad?->nombre_comunidad), 0, 3) ?: 'PAR';
        $iniciales = collect([explode(' ', trim((string) $productor->nombres))[0] ?? '', $productor->apellido_paterno, $productor->apellido_materno])
            ->map(fn ($p) => substr($sinTildes((string) $p), 0, 1))
            ->filter()
            ->implode('') ?: 'X';

        $prefijo = "{$comunidad}-{$iniciales}-";

        $maximo = Parcela::query()
            ->where('codigo_parcela', 'like', $prefijo.'%')
            ->pluck('codigo_parcela')
            ->map(fn ($c) => (int) substr($c, strlen($prefijo)))
            ->max() ?? 0;

        $n = $maximo + 1;

        while (Parcela::where('codigo_parcela', $prefijo.str_pad((string) $n, 2, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return ['codigo' => $prefijo.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'prefijo' => $prefijo];
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
