<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Desempeño del modelo de IA</title>

    <style>
        @page { margin: 20px 24px 30px 24px; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #0f172a;
            line-height: 1.35;
        }

        .header { border-bottom: 3px solid #15803d; padding-bottom: 8px; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 17px; color: #14532d; }
        .header p { margin: 3px 0 0; color: #475569; font-size: 9px; }

        .section-title {
            font-size: 11px; font-weight: bold; margin: 14px 0 6px; color: #14532d;
            text-transform: uppercase; letter-spacing: .03em;
        }

        .filters {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
            padding: 6px 8px; margin-bottom: 8px; color: #334155;
        }

        .aviso {
            background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px;
            padding: 6px 8px; margin-bottom: 6px; color: #92400e;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 5px; text-align: left; }
        th { background: #f0fdf4; color: #14532d; font-size: 8.5px; }
        td.num, th.num { text-align: right; }
        td.centro, th.centro { text-align: center; }

        .kpis td { border: 1px solid #bbf7d0; background: #f0fdf4; text-align: center; padding: 7px 4px; }
        .kpis .valor { font-size: 15px; font-weight: bold; color: #14532d; }
        .kpis .etq { font-size: 8px; color: #475569; }

        .diagonal { background: #dcfce7; font-weight: bold; }
        .error { background: #fee2e2; }

        .pie { margin-top: 12px; color: #64748b; font-size: 8px; }
    </style>
</head>

@php
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '.') . ' %';
    $frac = fn ($v) => $v === null ? '—' : number_format($v * 100, 1, ',', '.') . ' %';
    $r = $datos['resumen'];
    $g = $datos['metricas_globales'];
    $m = $datos['matriz'];
    $f = $datos['filtros'];
@endphp

<body>
    <div class="header">
        <h1>Desempeño del modelo de IA en producción</h1>
        <p>Sistema PROINPA · Calculado con los diagnósticos validados por un agrónomo</p>
        <p>Generado el {{ $fechaGeneracion }} por {{ $generadoPor }}</p>
    </div>

    <div class="filters">
        <strong>Filtros:</strong>
        Desde: {{ $f['fecha_desde'] ?? 'sin límite' }} ·
        Hasta: {{ $f['fecha_hasta'] ?? 'sin límite' }} ·
        Versión del modelo: {{ $f['modelo_version'] ?? 'todas' }}
    </div>

    @foreach ($datos['advertencias'] as $aviso)
        <div class="aviso">{{ $aviso }}</div>
    @endforeach

    <div class="section-title">Resumen</div>
    <table class="kpis">
        <tr>
            <td><div class="valor">{{ $frac($g['accuracy']) }}</div><div class="etq">Accuracy (aciertos)</div></td>
            <td><div class="valor">{{ $frac($g['f1_macro']) }}</div><div class="etq">F1 macro</div></td>
            <td><div class="valor">{{ $r['total_cnn'] }}</div><div class="etq">Diagnósticos de la IA</div></td>
            <td><div class="valor">{{ $r['validados_utiles'] }}</div><div class="etq">Evaluados (confirmados + corregidos)</div></td>
        </tr>
    </table>

    <table style="margin-top:6px">
        <tr>
            <th>Estado de validación</th><th class="num">Cantidad</th><th class="num">Porcentaje</th>
        </tr>
        <tr><td>Confirmados (la IA acertó)</td><td class="num">{{ $r['confirmados'] }}</td><td class="num">{{ $pct($r['pct_confirmados']) }}</td></tr>
        <tr><td>Corregidos (la IA se equivocó)</td><td class="num">{{ $r['corregidos'] }}</td><td class="num">{{ $pct($r['pct_corregidos']) }}</td></tr>
        <tr><td>Rechazados</td><td class="num">{{ $r['rechazados'] }}</td><td class="num">{{ $pct($r['pct_rechazados']) }}</td></tr>
        <tr><td>Pendientes de validar</td><td class="num">{{ $r['pendientes'] }}</td><td class="num">{{ $pct($r['pct_pendientes']) }}</td></tr>
    </table>

    <div class="section-title">Matriz de confusión</div>
    <p style="margin:0 0 4px;color:#475569">Filas: enfermedad final (real). Columnas: lo que predijo la IA. La diagonal son los aciertos.</p>
    @if (count($m['filas_reales']) > 0)
        <table>
            <tr>
                <th>Real \ Predicho</th>
                @foreach ($m['columnas_predichas'] as $col)
                    <th class="centro">{{ $m['nombres'][$col] }}</th>
                @endforeach
                <th class="centro">Total</th>
            </tr>
            @foreach ($m['filas_reales'] as $i => $fila)
                <tr>
                    <th>{{ $m['nombres'][$fila] }}</th>
                    @foreach ($m['valores'][$i] as $j => $valor)
                        <td class="centro {{ $i === $j ? 'diagonal' : ($valor > 0 ? 'error' : '') }}">{{ $valor }}</td>
                    @endforeach
                    <td class="centro">{{ array_sum($m['valores'][$i]) }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p>Todavía no hay diagnósticos validados para calcular la matriz.</p>
    @endif

    <div class="section-title">Precisión, recall y F1 por clase</div>
    <table>
        <tr>
            <th>Clase</th><th class="num">Casos reales</th><th class="num">Predichos</th>
            <th class="num">Precisión</th><th class="num">Recall</th><th class="num">F1</th>
        </tr>
        @forelse ($datos['por_clase'] as $c)
            <tr>
                <td>{{ $c['nombre'] }}</td><td class="num">{{ $c['soporte'] }}</td><td class="num">{{ $c['predichos'] }}</td>
                <td class="num">{{ $frac($c['precision']) }}</td><td class="num">{{ $frac($c['recall']) }}</td><td class="num">{{ $frac($c['f1']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin datos.</td></tr>
        @endforelse
    </table>

    <div class="section-title">Confianza promedio de la IA</div>
    @php $c = $datos['confianza']; @endphp
    <table>
        <tr><th>Grupo</th><th class="num">Casos</th><th class="num">Confianza promedio</th></tr>
        <tr><td>Aciertos (confirmados)</td><td class="num">{{ $c['n_aciertos'] }}</td><td class="num">{{ $pct($c['promedio_aciertos']) }}</td></tr>
        <tr><td>Errores (corregidos)</td><td class="num">{{ $c['n_errores'] }}</td><td class="num">{{ $pct($c['promedio_errores']) }}</td></tr>
        <tr><td>Rechazados</td><td class="num">{{ $c['n_rechazados'] }}</td><td class="num">{{ $pct($c['promedio_rechazados']) }}</td></tr>
    </table>

    <div class="section-title">Evolución mensual del porcentaje de aciertos</div>
    <table>
        <tr>
            <th>Mes</th><th class="num">Confirmados</th><th class="num">Corregidos</th>
            <th class="num">Rechazados</th><th class="num">% de aciertos</th>
        </tr>
        @forelse ($datos['evolucion_mensual'] as $mes)
            <tr>
                <td>{{ $mes['mes'] }}</td><td class="num">{{ $mes['confirmados'] }}</td><td class="num">{{ $mes['corregidos'] }}</td>
                <td class="num">{{ $mes['rechazados'] }}</td><td class="num">{{ $pct($mes['aciertos_pct']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin diagnósticos validados en el período.</td></tr>
        @endforelse
    </table>

    <p class="pie">
        Las métricas miden el acuerdo entre la IA y el criterio del agrónomo. Un valor bajo no siempre implica un modelo malo:
        también depende de la calidad de las fotos y de la cantidad de casos validados.
    </p>
</body>

</html>
