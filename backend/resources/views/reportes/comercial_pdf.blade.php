<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $reporte['titulo'] ?? 'Reporte' }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        .header {
            border-bottom: 3px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #1d4ed8;
        }

        .header p {
            margin: 4px 0 0;
            color: #4b5563;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin: 14px 0 8px;
            color: #111827;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .summary td {
            border: 1px solid #d1d5db;
            padding: 6px;
        }

        .summary td:first-child {
            background: #eff6ff;
            font-weight: bold;
            color: #1e40af;
            width: 25%;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th {
            background: #1d4ed8;
            color: #ffffff;
            padding: 6px;
            border: 1px solid #1e40af;
            font-size: 9px;
        }

        table.report td {
            padding: 5px;
            border: 1px solid #d1d5db;
            font-size: 8px;
        }

        table.report tr:nth-child(even) td {
            background: #f9fafb;
        }

        .filters {
            margin-bottom: 12px;
            font-size: 9px;
            color: #374151;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $reporte['titulo'] ?? 'Reporte' }}</h1>
        <p>Generado: {{ $fechaGeneracion }}</p>
        <p>Sistema de gestión comercial e inventario</p>
    </div>

    @if (!empty($filtros))
    <div class="section-title">Filtros aplicados</div>

    <div class="filters">
        @foreach ($filtros as $key => $value)
        @if ($value !== null && $value !== '')
        <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
        {{ $value }}
        &nbsp;&nbsp;
        @endif
        @endforeach
    </div>
    @endif

    <div class="section-title">Resumen</div>

    <table class="summary">
        @foreach (($reporte['resumen'] ?? []) as $key => $value)
        <tr>
            <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
            <td>{{ $value }}</td>
        </tr>
        @endforeach
    </table>

    <div class="section-title">Detalle del reporte</div>

    <table class="report">
        <thead>
            <tr>
                @foreach (($reporte['columnas'] ?? []) as $columna)
                <th>{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @forelse (($reporte['filas'] ?? []) as $fila)
            <tr>
                @foreach ($fila as $valor)
                <td>{{ $valor }}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($reporte['columnas'] ?? []) }}">
                    No existen datos para los filtros seleccionados.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Reporte generado automáticamente por el sistema.
    </div>
</body>

</html>