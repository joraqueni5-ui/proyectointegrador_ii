<?php

namespace App\Services\Diagnostico;

use App\Models\Inspeccion;

class ReporteFitosanitarioService
{
    private array $relations = [
        'usuario:id,name,paterno,materno',
        'programadoPor:id,name,paterno,materno',
        'validadoPor:id,name,paterno,materno',
        'parcela:id,codigo_parcela,nombre_referencial,id_productor',
        'parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno',
        'cultivo',
        'etapaFenologica',
        'diagnosticos.enfermedad',
        'diagnosticos.validadoPor:id,name,paterno,materno',
        'diagnosticos.alertas',
    ];

    public function generar(string $tipo, array $filtros = []): array
    {
        return match ($tipo) {
            'inspecciones' => $this->inspeccionesDetalladas($filtros),
            'diagnosticos' => $this->diagnosticosDetallados($filtros),
            'por_parcela' => $this->porParcela($filtros),
            'por_tecnico' => $this->porTecnico($filtros),
            'por_enfermedad' => $this->porEnfermedad($filtros),
            'alertas' => $this->alertas($filtros),
            'no_realizadas' => $this->noRealizadas($filtros),
            'pre_siembra' => $this->preSiembra($filtros),
            default => $this->general($filtros),
        };
    }

    private function baseQuery(array $filtros)
    {
        // Los filtros de diagnóstico y alerta también se aplican a las relaciones
        // cargadas; si no, se listarían diagnósticos y alertas que no coinciden.
        $filtrarDiagnosticos = function ($q) use ($filtros) {
            $q->when($filtros['id_enfermedad'] ?? null, fn ($s, $id) => $s->where('id_enfermedad', $id))
                ->when($filtros['nivel_severidad'] ?? null, fn ($s, $nivel) => $s->where('nivel_severidad', $nivel))
                ->when($filtros['estado_validacion'] ?? null, fn ($s, $estado) => $s->where('estado_validacion', $estado));
        };

        $filtrarAlertas = function ($q) use ($filtros) {
            $q->when($filtros['nivel_riesgo'] ?? null, function ($s, $nivel) {
                in_array($nivel, ['Critico', 'Crítico'], true)
                    ? $s->whereIn('nivel_riesgo', ['Critico', 'Crítico'])
                    : $s->where('nivel_riesgo', $nivel);
            })
                ->when($filtros['estado_alerta'] ?? null, fn ($s, $estado) => $s->where('estado', $estado));
        };

        $relaciones = [
            ...$this->relations,
            'diagnosticos' => $filtrarDiagnosticos,
            'diagnosticos.alertas' => $filtrarAlertas,
        ];

        return Inspeccion::with($relaciones)
            ->when($filtros['fecha_inicio'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha_visita', '>=', $fecha))
            ->when($filtros['fecha_fin'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha_visita', '<=', $fecha))
            ->when($filtros['id_parcela'] ?? null, fn ($q, $id) => $q->where('id_parcela', $id))
            ->when($filtros['id_usuario'] ?? null, fn ($q, $id) => $q->where('id_usuario', $id))
            ->when($filtros['tipo_inspeccion'] ?? null, fn ($q, $tipo) => $q->where('tipo_inspeccion', $tipo))
            ->when($filtros['estado_inspeccion'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['id_productor'] ?? null, function ($q, $id) {
                $q->whereHas('parcela', fn ($sub) => $sub->where('id_productor', $id));
            })
            ->when($filtros['id_enfermedad'] ?? null, function ($q, $id) {
                $q->whereHas('diagnosticos', fn ($sub) => $sub->where('id_enfermedad', $id));
            })
            ->when($filtros['nivel_severidad'] ?? null, function ($q, $nivel) {
                $q->whereHas('diagnosticos', fn ($sub) => $sub->where('nivel_severidad', $nivel));
            })
            ->when($filtros['estado_validacion'] ?? null, function ($q, $estado) {
                $q->whereHas('diagnosticos', fn ($sub) => $sub->where('estado_validacion', $estado));
            })
            ->when($filtros['nivel_riesgo'] ?? null, function ($q, $nivel) {
                $q->whereHas('diagnosticos.alertas', function ($sub) use ($nivel) {
                    if (in_array($nivel, ['Critico', 'Crítico'], true)) {
                        $sub->whereIn('nivel_riesgo', ['Critico', 'Crítico']);

                        return;
                    }

                    $sub->where('nivel_riesgo', $nivel);
                });
            })
            ->when($filtros['estado_alerta'] ?? null, function ($q, $estado) {
                $q->whereHas('diagnosticos.alertas', fn ($sub) => $sub->where('estado', $estado));
            })
            ->when($filtros['busqueda'] ?? null, function ($q, $busqueda) {
                $like = '%'.mb_strtolower(trim($busqueda)).'%';

                $q->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(observaciones) LIKE ?', [$like])
                        ->orWhereHas('parcela', function ($p) use ($like) {
                            $p->whereRaw('LOWER(codigo_parcela) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(nombre_referencial) LIKE ?', [$like]);
                        })
                        ->orWhereHas('parcela.productor', function ($p) use ($like) {
                            $p->whereRaw('LOWER(nombre_completo) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(nombres) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(apellido_paterno) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(apellido_materno) LIKE ?', [$like]);
                        })
                        ->orWhereHas('diagnosticos.enfermedad', function ($e) use ($like) {
                            $e->whereRaw('LOWER(nombre_comun) LIKE ?', [$like]);
                        });
                });
            });
    }

    private function general(array $filtros): array
    {
        $items = $this->baseQuery($filtros)
            ->orderByDesc('fecha_visita')
            ->orderByDesc('id')
            ->get();

        return [
            'titulo' => 'Reporte fitosanitario general',
            'tipo' => 'fitosanitario_general',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Fecha',
                'Parcela / productor',
                'Técnico',
                'Inspección',
                'Cultivo',
                'Diagnóstico',
                'Alerta / riesgo',
                'Acción u observación',
            ],
            'filas' => $this->filasGenerales($items),
        ];
    }

    private function porParcela(array $filtros): array
    {
        $items = $this->baseQuery($filtros)->get();

        $filas = $items
            ->groupBy(fn ($i) => $i->parcela?->id ?? 'sin_parcela')
            ->map(function ($grupo) {
                $primero = $grupo->first();
                $diagnosticos = $this->diagnosticosDe($grupo);
                $alertas = $this->alertasDe($grupo);

                return [
                    'parcela' => $this->nombreParcela($primero),
                    'productor' => $this->nombreProductor($primero->parcela?->productor),
                    'ultima_inspeccion' => $this->fecha($grupo->max('fecha_visita')),
                    'actividad' => $this->resumenActividad($grupo, $diagnosticos, $alertas),
                    'sanidad_detectada' => $this->enfermedadesTexto($diagnosticos),
                    'riesgo_principal' => $this->riesgosTexto($alertas),
                    'seguimiento' => $this->estadoTrabajo($grupo),
                    'accion_recomendada' => $this->accionesTexto($alertas),
                ];
            })
            ->values();

        return [
            'titulo' => 'Reporte fitosanitario por parcela',
            'tipo' => 'fitosanitario_por_parcela',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Parcela',
                'Productor',
                'Última inspección',
                'Actividad',
                'Sanidad detectada',
                'Riesgo principal',
                'Seguimiento',
                'Acción recomendada',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function porTecnico(array $filtros): array
    {
        $items = $this->baseQuery($filtros)->get();

        $filas = $items
            ->groupBy(fn ($i) => $i->usuario?->id ?? 'sin_tecnico')
            ->map(function ($grupo) {
                $primero = $grupo->first();
                $diagnosticos = $this->diagnosticosDe($grupo);
                $alertas = $this->alertasDe($grupo);

                return [
                    'tecnico' => $this->nombreUsuario($primero->usuario),
                    'parcelas_atendidas' => $grupo->pluck('id_parcela')->unique()->count(),
                    'ultima_visita' => $this->fecha($grupo->max('fecha_visita')),
                    'actividad' => $this->resumenActividad($grupo, $diagnosticos, $alertas),
                    'resultado_principal' => $this->enfermedadesTexto($diagnosticos),
                    'riesgo_generado' => $this->riesgosTexto($alertas),
                    'estado_de_trabajo' => $this->estadoTrabajo($grupo),
                ];
            })
            ->values();

        return [
            'titulo' => 'Reporte fitosanitario por técnico asignado',
            'tipo' => 'fitosanitario_por_tecnico',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Técnico',
                'Parcelas atendidas',
                'Última visita',
                'Actividad',
                'Resultado principal',
                'Riesgo generado',
                'Estado de trabajo',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function porEnfermedad(array $filtros): array
    {
        $items = $this->baseQuery($filtros)->get();
        $diagnosticos = $this->diagnosticosDe($items);

        $filas = $diagnosticos
            ->groupBy(fn ($item) => $item['diagnostico']->enfermedad?->nombre_comun ?? 'Sin enfermedad asignada')
            ->map(function ($grupo, $enfermedad) {
                $alertas = collect();

                foreach ($grupo as $item) {
                    foreach ($item['diagnostico']->alertas as $alerta) {
                        $alertas->push($alerta);
                    }
                }

                return [
                    'enfermedad' => $enfermedad,
                    'diagnosticos' => $grupo->count(),
                    'parcelas_afectadas' => $grupo->pluck('inspeccion.id_parcela')->unique()->count(),
                    'confianza_promedio' => number_format((float) $grupo->avg(fn ($item) => $item['diagnostico']->porcentaje_confianza), 1).'%',
                    'severidad_dominante' => $this->limpiar($this->valorFrecuente($grupo->map(fn ($item) => $item['diagnostico']->nivel_severidad))),
                    'riesgo_principal' => $this->riesgosTexto($alertas),
                    'parcelas_relevantes' => $this->recortar($grupo->map(fn ($item) => $this->nombreParcela($item['inspeccion']))->unique()->implode(', '), 90),
                    'accion_recomendada' => $this->accionesTexto($alertas),
                ];
            })
            ->values();

        return [
            'titulo' => 'Reporte fitosanitario por enfermedad',
            'tipo' => 'fitosanitario_por_enfermedad',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Enfermedad',
                'Diagnósticos',
                'Parcelas afectadas',
                'Confianza promedio',
                'Severidad dominante',
                'Riesgo principal',
                'Parcelas relevantes',
                'Acción recomendada',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function alertas(array $filtros): array
    {
        $items = $this->baseQuery($filtros)->get();
        $alertas = $this->alertasDe($items);

        $filas = $alertas->map(function ($item) {
            $alerta = $item['alerta'];
            $diagnostico = $item['diagnostico'];
            $inspeccion = $item['inspeccion'];

            return [
                'fecha' => $this->fecha($alerta->fecha_emision),
                'parcela' => $this->nombreParcela($inspeccion),
                'productor' => $this->nombreProductor($inspeccion->parcela?->productor),
                'enfermedad' => $diagnostico->enfermedad?->nombre_comun ?? '—',
                'riesgo' => $this->limpiar($alerta->nivel_riesgo),
                'estado' => $this->limpiar($alerta->estado),
                'mensaje' => $alerta->mensaje ?? '—',
                'accion_tomada' => $alerta->accion_tomada ?? '—',
                'fecha_limite' => $this->fecha($alerta->fecha_limite_accion),
            ];
        })->values();

        return [
            'titulo' => 'Reporte de alertas fitosanitarias',
            'tipo' => 'fitosanitario_alertas',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Fecha',
                'Parcela',
                'Productor',
                'Enfermedad',
                'Riesgo',
                'Estado',
                'Mensaje',
                'Acción tomada',
                'Fecha límite',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function noRealizadas(array $filtros): array
    {
        $filtros['estado_inspeccion'] = 'No_realizada';

        $items = $this->baseQuery($filtros)
            ->orderByDesc('fecha_visita')
            ->get();

        $filas = $items->map(fn ($i) => [
            'fecha' => $this->fecha($i->fecha_visita),
            'parcela' => $this->nombreParcela($i),
            'productor' => $this->nombreProductor($i->parcela?->productor),
            'tecnico' => $this->nombreUsuario($i->usuario),
            'tipo' => $this->limpiar($i->tipo_inspeccion ?? 'Cultivo'),
            'motivo' => $i->motivo_no_realizada ?? '—',
            'fecha_reprogramada' => $this->fecha($i->fecha_reprogramada),
            'recomendacion' => $i->recomendacion_reprogramacion ?? '—',
        ])->values();

        return [
            'titulo' => 'Reporte de inspecciones no realizadas',
            'tipo' => 'fitosanitario_no_realizadas',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Fecha',
                'Parcela',
                'Productor',
                'Técnico',
                'Tipo',
                'Motivo',
                'Fecha reprogramada',
                'Recomendación',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function preSiembra(array $filtros): array
    {
        $filtros['tipo_inspeccion'] = 'Pre_siembra';

        $items = $this->baseQuery($filtros)
            ->orderByDesc('fecha_visita')
            ->get();

        $filas = $items->map(fn ($i) => [
            'fecha' => $this->fecha($i->fecha_visita),
            'parcela' => $this->nombreParcela($i),
            'productor' => $this->nombreProductor($i->parcela?->productor),
            'tecnico' => $this->nombreUsuario($i->usuario),
            'estado' => $this->limpiar($i->estado),
            'condicion_terreno' => $i->condicion_terreno ?? '—',
            'recomendacion' => $i->recomendacion_pre_siembra ?? '—',
            'observaciones' => $i->observaciones ?? '—',
        ])->values();

        return [
            'titulo' => 'Reporte de inspecciones de pre siembra',
            'tipo' => 'fitosanitario_pre_siembra',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Fecha',
                'Parcela',
                'Productor',
                'Técnico',
                'Estado',
                'Condición terreno',
                'Recomendación',
                'Observaciones',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function inspeccionesDetalladas(array $filtros): array
    {
        $items = $this->baseQuery($filtros)
            ->orderByDesc('fecha_visita')
            ->orderByDesc('id')
            ->get();

        $filas = $items->map(function ($i) {
            $tipo = $i->tipo_inspeccion ?? 'Cultivo';

            $etapaCondicion = '—';

            if ($tipo === 'Pre_siembra') {
                $etapaCondicion = $i->condicion_terreno ?: 'Pre siembra';
            } else {
                $etapaCondicion = $i->etapaFenologica?->nombre ?: '—';
            }

            $hallazgo = '—';

            if ($i->estado === 'No_realizada') {
                $hallazgo = $i->motivo_no_realizada ?: 'No se registró motivo.';
            } elseif ($tipo === 'Pre_siembra') {
                $hallazgo = $i->recomendacion_pre_siembra
                    ?: $i->observaciones
                    ?: 'Sin recomendación registrada.';
            } elseif ($i->pct_plantas_afectadas !== null) {
                $hallazgo = 'Afectación registrada: '.$i->pct_plantas_afectadas.'%';
            } else {
                $hallazgo = $i->observaciones ?: 'Sin afectación registrada.';
            }

            return [
                'fecha' => $this->fecha($i->fecha_visita),
                'tipo' => $this->limpiar($tipo),
                'estado' => $this->limpiar($i->estado),
                'parcela' => $this->nombreParcela($i),
                'productor' => $this->nombreProductor($i->parcela?->productor),
                'tecnico' => $this->nombreUsuario($i->usuario),
                'cultivo' => $tipo === 'Pre_siembra'
                    ? 'No aplica'
                    : ($i->cultivo?->variedad_papa ?? 'No registrado'),
                'etapa_condicion' => $this->recortar($etapaCondicion, 45),
                'hallazgo_principal' => $this->recortar($hallazgo, 80),
                'diagnosticos' => $i->diagnosticos->count(),
                'observaciones' => $this->recortar($i->observaciones ?? '—', 80),
            ];
        })->values();

        return [
            'titulo' => 'Reporte resumido de inspecciones',
            'tipo' => 'fitosanitario_inspecciones',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Fecha',
                'Tipo',
                'Estado',
                'Parcela',
                'Productor',
                'Técnico',
                'Cultivo',
                'Etapa / condición',
                'Hallazgo principal',
                'Diagnósticos',
                'Observaciones',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function diagnosticosDetallados(array $filtros): array
    {
        $items = $this->baseQuery($filtros)
            ->orderByDesc('fecha_visita')
            ->orderByDesc('id')
            ->get();

        $diagnosticos = $this->diagnosticosDe($items);

        $filas = $diagnosticos->map(function ($item) {
            $inspeccion = $item['inspeccion'];
            $diagnostico = $item['diagnostico'];
            $alertas = $diagnostico->alertas ?? collect();

            return [
                'fecha' => $this->fecha($inspeccion->fecha_visita),
                'parcela_productor' => $this->nombreParcela($inspeccion).' / '.$this->nombreProductor($inspeccion->parcela?->productor),
                'tecnico' => $this->nombreUsuario($inspeccion->usuario),
                'cultivo' => $inspeccion->cultivo?->variedad_papa ?? 'No aplica',
                'diagnostico' => $this->recortar(($diagnostico->enfermedad?->nombre_comun ?? 'Sin enfermedad').' · Confianza '.($diagnostico->porcentaje_confianza !== null ? $diagnostico->porcentaje_confianza.'%' : '—'), 90),
                'severidad' => $this->limpiar($diagnostico->nivel_severidad),
                'validacion' => $this->limpiar($diagnostico->estado_validacion),
                'alerta' => $this->recortar('Riesgo: '.($alertas->pluck('nivel_riesgo')->filter()->unique()->implode(', ') ?: '—').' · Estado: '.($alertas->pluck('estado')->filter()->unique()->implode(', ') ?: '—'), 90),
                'recomendacion' => $this->recortar($alertas->pluck('accion_tomada')->filter()->unique()->implode(' | ') ?: $diagnostico->recomendacion ?: '—', 100),
            ];
        })->values();

        return [
            'titulo' => 'Reporte de diagnósticos fitosanitarios',
            'tipo' => 'fitosanitario_diagnosticos',
            'resumen' => $this->resumen($items),
            'columnas' => [
                'Fecha',
                'Parcela / productor',
                'Técnico',
                'Cultivo',
                'Diagnóstico',
                'Severidad',
                'Validación',
                'Alerta',
                'Recomendación',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function filasGenerales($items): array
    {
        $filas = collect();

        foreach ($items as $inspeccion) {
            if ($inspeccion->diagnosticos->isEmpty()) {
                $filas->push($this->filaGeneral($inspeccion, null));

                continue;
            }

            foreach ($inspeccion->diagnosticos as $diagnostico) {
                $filas->push($this->filaGeneral($inspeccion, $diagnostico));
            }
        }

        return $filas->values()->toArray();
    }

    private function filaGeneral($inspeccion, $diagnostico): array
    {
        $alertas = $diagnostico?->alertas ?? collect();

        $diagnosticoTexto = $diagnostico
            ? ($diagnostico->enfermedad?->nombre_comun ?? 'Sin enfermedad asignada')
            .' · '.$this->limpiar($diagnostico->nivel_severidad)
            .' · '.($diagnostico->porcentaje_confianza !== null ? $diagnostico->porcentaje_confianza.'%' : 'sin confianza')
            : 'Sin diagnóstico registrado';

        $alertaTexto = $alertas->isNotEmpty()
            ? 'Riesgo: '.($alertas->pluck('nivel_riesgo')->filter()->unique()->implode(', ') ?: '—')
            .' · Estado: '.($alertas->pluck('estado')->filter()->unique()->implode(', ') ?: '—')
            : 'Sin alerta generada';

        $accion = $alertas->pluck('accion_tomada')->filter()->unique()->implode(' | ')
            ?: $diagnostico?->recomendacion
            ?: $inspeccion->observaciones
            ?: 'Sin acción registrada';

        return [
            'fecha' => $this->fecha($inspeccion->fecha_visita),
            'parcela_productor' => $this->nombreParcela($inspeccion).' / '.$this->nombreProductor($inspeccion->parcela?->productor),
            'tecnico' => $this->nombreUsuario($inspeccion->usuario),
            'inspeccion' => $this->limpiar($inspeccion->tipo_inspeccion ?? 'Cultivo').' · '.$this->limpiar($inspeccion->estado),
            'cultivo' => $inspeccion->cultivo?->variedad_papa ?? 'No aplica',
            'diagnostico' => $this->recortar($diagnosticoTexto, 85),
            'alerta_riesgo' => $this->recortar($alertaTexto, 80),
            'accion_observacion' => $this->recortar($accion, 100),
        ];
    }

    private function resumen($items): array
    {
        $diagnosticos = $this->diagnosticosDe($items);
        $alertas = $this->alertasDe($items);

        $enfermedadPrincipal = $this->valorFrecuente(
            $diagnosticos->map(fn ($item) => $item['diagnostico']->enfermedad?->nombre_comun)
        );

        $riesgoPrincipal = $this->valorFrecuente(
            $alertas->map(fn ($item) => $item['alerta']->nivel_riesgo)
        );

        return [
            'inspecciones' => $items->count(),
            'parcelas_evaluadas' => $items->pluck('id_parcela')->filter()->unique()->count(),
            'productores_atendidos' => $items->map(fn ($i) => $i->parcela?->id_productor)->filter()->unique()->count(),
            'diagnosticos' => $diagnosticos->count(),
            'alertas_activas' => $alertas->filter(fn ($item) => $item['alerta']->estado === 'Activa')->count(),
            'alertas_atendidas' => $alertas->filter(fn ($item) => $item['alerta']->estado === 'Atendida')->count(),
            'riesgo_alto_critico' => $alertas->filter(fn ($item) => in_array($item['alerta']->nivel_riesgo, ['Alto', 'Critico', 'Crítico'], true))->count(),
            'enfermedad_principal' => $enfermedadPrincipal ?: 'Sin enfermedad predominante',
            'riesgo_principal' => $this->limpiar($riesgoPrincipal ?: 'Sin riesgo registrado'),
            'estado_predominante' => $this->limpiar($this->valorFrecuente($items->pluck('estado')) ?: 'Sin estado'),
        ];
    }

    private function resumenActividad($grupo, $diagnosticos, $alertas): string
    {
        return $grupo->count().' inspección(es), '
            .$diagnosticos->count().' diagnóstico(s), '
            .$alertas->count().' alerta(s)';
    }

    private function estadoTrabajo($grupo): string
    {
        $partes = [];

        $mapa = [
            'Pendiente' => 'pendiente(s)',
            'En_proceso' => 'en proceso',
            'Completada' => 'completada(s)',
            'Validada' => 'validada(s)',
            'No_realizada' => 'no realizada(s)',
        ];

        foreach ($mapa as $estado => $label) {
            $cantidad = $grupo->where('estado', $estado)->count();

            if ($cantidad > 0) {
                $partes[] = $cantidad.' '.$label;
            }
        }

        return $partes ? implode(' · ', $partes) : 'Sin seguimiento registrado';
    }

    private function enfermedadesTexto($diagnosticos, int $limite = 90): string
    {
        $texto = $diagnosticos
            ->map(fn ($item) => $item['diagnostico']->enfermedad?->nombre_comun)
            ->filter()
            ->unique()
            ->implode(', ');

        return $texto ? $this->recortar($texto, $limite) : 'Sin enfermedad registrada';
    }

    private function riesgosTexto($alertas, int $limite = 80): string
    {
        $texto = $alertas
            ->map(function ($item) {
                $alerta = is_array($item) ? $item['alerta'] : $item;

                return $alerta->nivel_riesgo ?? null;
            })
            ->filter()
            ->unique()
            ->map(fn ($riesgo) => $this->limpiar($riesgo))
            ->implode(', ');

        return $texto ? $this->recortar($texto, $limite) : 'Sin alerta de riesgo';
    }

    private function accionesTexto($alertas, int $limite = 100): string
    {
        $texto = $alertas
            ->map(function ($item) {
                $alerta = is_array($item) ? $item['alerta'] : $item;

                return $alerta->accion_tomada ?? null;
            })
            ->filter()
            ->unique()
            ->implode(' | ');

        return $texto ? $this->recortar($texto, $limite) : 'Sin acción registrada';
    }

    private function valorFrecuente($valores): ?string
    {
        $valores = collect($valores)
            ->filter(fn ($valor) => $valor !== null && $valor !== '')
            ->map(fn ($valor) => (string) $valor);

        if ($valores->isEmpty()) {
            return null;
        }

        return $valores->countBy()->sortDesc()->keys()->first();
    }

    private function diagnosticosDe($inspecciones)
    {
        $items = collect();

        foreach ($inspecciones as $inspeccion) {
            foreach ($inspeccion->diagnosticos as $diagnostico) {
                $items->push([
                    'inspeccion' => $inspeccion,
                    'diagnostico' => $diagnostico,
                ]);
            }
        }

        return $items;
    }

    private function alertasDe($inspecciones)
    {
        $items = collect();

        foreach ($inspecciones as $inspeccion) {
            foreach ($inspeccion->diagnosticos as $diagnostico) {
                foreach ($diagnostico->alertas as $alerta) {
                    $items->push([
                        'inspeccion' => $inspeccion,
                        'diagnostico' => $diagnostico,
                        'alerta' => $alerta,
                    ]);
                }
            }
        }

        return $items;
    }

    private function nombreUsuario($user): string
    {
        if (! $user) {
            return '—';
        }

        return collect([
            $user->name,
            $user->paterno,
            $user->materno,
        ])->filter()->implode(' ') ?: '—';
    }

    private function nombreProductor($productor): string
    {
        if (! $productor) {
            return '—';
        }

        return $productor->nombre_completo ?: collect([
            $productor->nombres,
            $productor->apellido_paterno,
            $productor->apellido_materno,
        ])->filter()->implode(' ') ?: '—';
    }

    private function nombreParcela($inspeccion): string
    {
        $parcela = $inspeccion->parcela;

        if (! $parcela) {
            return '—';
        }

        return $parcela->nombre_referencial
            ? $parcela->codigo_parcela.' — '.$parcela->nombre_referencial
            : $parcela->codigo_parcela;
    }

    private function fecha($value): string
    {
        if (! $value) {
            return '—';
        }

        return method_exists($value, 'format')
            ? $value->format('d/m/Y')
            : (string) $value;
    }

    private function limpiar($valor): string
    {
        if (! $valor) {
            return '—';
        }

        return str_replace('_', ' ', (string) $valor);
    }

    private function recortar($texto, int $limite = 80): string
    {
        if (! $texto) {
            return '—';
        }

        $texto = trim((string) $texto);

        if ($texto === '') {
            return '—';
        }

        if (strlen($texto) <= $limite) {
            return $texto;
        }

        return substr($texto, 0, $limite).'...';
    }
}
