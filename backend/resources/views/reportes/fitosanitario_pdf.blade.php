<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $reporte['titulo'] ?? 'Reporte fitosanitario' }}</title>

    <style>
        @page {
            margin: 18px 22px 28px 22px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #0f172a;
            line-height: 1.35;
        }

        .header {
            border-bottom: 3px solid #15803d;
            padding-bottom: 9px;
            margin-bottom: 10px;
        }

        .header-top {
            width: 100%;
            border-collapse: collapse;
        }

        .brand {
            width: 58px;
            height: 58px;
            border-radius: 12px;
            background: #dcfce7;
            color: #15803d;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            line-height: 58px;
            border: 1px solid #86efac;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #14532d;
            line-height: 1.15;
        }

        .header p {
            margin: 3px 0 0;
            color: #475569;
            font-size: 9px;
        }

        .meta {
            text-align: right;
            color: #475569;
            font-size: 8.5px;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin: 10px 0 7px;
            color: #14532d;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .filters {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 7px 8px;
            margin-bottom: 10px;
            color: #334155;
        }

        .filter-item {
            display: inline-block;
            margin-right: 9px;
            margin-bottom: 3px;
        }

        .filter-item strong {
            color: #166534;
        }

        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 0 -6px 8px -6px;
        }

        .summary td {
            width: 20%;
            border: 1px solid #dbeafe;
            background: #f8fafc;
            border-radius: 8px;
            padding: 7px;
            vertical-align: top;
        }

        .summary .label {
            display: block;
            color: #64748b;
            font-size: 7.4px;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .summary .value {
            color: #0f172a;
            font-size: 11px;
            font-weight: bold;
            word-break: break-word;
        }

        .notice {
            border-left: 4px solid #15803d;
            background: #ecfdf5;
            color: #14532d;
            padding: 7px 9px;
            margin: 8px 0 10px;
            font-size: 8.5px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report th {
            background: #15803d;
            color: #ffffff;
            padding: 5px 4px;
            border: 1px solid #166534;
            font-size: 7.4px;
            line-height: 1.2;
            text-align: left;
            vertical-align: middle;
            word-wrap: break-word;
        }

        table.report td {
            padding: 4px;
            border: 1px solid #d1d5db;
            font-size: 7.2px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.report tr:nth-child(even) td {
            background: #f9fafb;
        }

        table.report tr {
            page-break-inside: avoid;
        }

        .empty {
            text-align: center;
            color: #64748b;
            padding: 12px;
        }

        .footer {
            position: fixed;
            bottom: -17px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>

<body>
    @php
    $resumen = $reporte['resumen'] ?? [];
    $columnas = $reporte['columnas'] ?? [];
    $filas = $reporte['filas'] ?? [];
    $limitePdf = 45;
    $filasPdf = array_slice($filas, 0, $limitePdf);
    $filasRestantes = max(count($filas) - count($filasPdf), 0);

    $label = function ($value) {
    return ucfirst(str_replace('_', ' ', (string) $value));
    };
    @endphp

    <div class="header">
        <table class="header-top">
            <tr>
                <td style="width:68px;">
                    <div class="brand">P</div>
                </td>
                <td>
                    <h1>{{ $reporte['titulo'] ?? 'Reporte fitosanitario' }}</h1>
                    <p>Sistema PROINPA — Seguimiento fitosanitario de parcelas, diagnósticos y alertas.</p>
                </td>
                <td class="meta" style="width:185px;">
                    <strong>Fecha de generación</strong><br>
                    {{ $fechaGeneracion }}<br>
                    <strong>Total de filas</strong><br>
                    {{ count($filas) }} registro(s)
                </td>
            </tr>
        </table>
    </div>

    @if (!empty(array_filter($filtros ?? [])))
    <div class="section-title">Filtros aplicados</div>
    <div class="filters">
        @foreach ($filtros as $key => $value)
        @if ($value !== null && $value !== '')
        <span class="filter-item">
            <strong>{{ $label($key) }}:</strong> {{ $value }}
        </span>
        @endif
        @endforeach
    </div>
    @endif

    <div class="section-title">Resumen ejecutivo</div>

    <table class="summary">
        @foreach (array_chunk($resumen, 5, true) as $grupo)
        <tr>
            @foreach ($grupo as $key => $value)
            <td>
                <span class="label">{{ $label($key) }}</span>
                <span class="value">{{ $value }}</span>
            </td>
            @endforeach

            @for ($i = count($grupo); $i < 5; $i++)
                <td>
                </td>
                @endfor
        </tr>
        @endforeach
    </table>

    <div class="section-title">Detalle principal</div>

    @if ($filasRestantes > 0)
    <div class="notice">
        Para evitar cortes y mantener el diseño listo para impresión, este PDF muestra los primeros
        {{ $limitePdf }} registros del detalle. El Excel conserva el reporte completo con {{ count($filas) }} registros.
    </div>
    @endif

    <table class="report">
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                <th>{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @forelse ($filasPdf as $fila)
            <tr>
                @foreach ($fila as $valor)
                <td>{{ $valor ?: '—' }}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td class="empty" colspan="{{ max(count($columnas), 1) }}">
                    No existen datos para los filtros seleccionados.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Reporte generado automáticamente por el sistema PROINPA. Documento de apoyo para revisión técnica y seguimiento fitosanitario.
    </div>
</body>

</html>