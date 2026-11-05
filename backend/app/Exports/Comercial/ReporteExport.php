<?php

namespace App\Exports\Comercial;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly array $reporte,
        private readonly array $filtros = []
    ) {}

    public function array(): array
    {
        $data = [];

        $data[] = [$this->reporte['titulo'] ?? 'Reporte'];
        $data[] = ['Generado:', now()->format('d/m/Y H:i')];
        $data[] = ['Tipo:', $this->reporte['tipo'] ?? '—'];

        if (! empty($this->filtros)) {
            $data[] = [];
            $data[] = ['Filtros aplicados'];

            foreach ($this->filtros as $key => $value) {
                if ($value !== null && $value !== '') {
                    $data[] = [$this->formatearEtiqueta($key), $value];
                }
            }
        }

        $data[] = [];
        $data[] = ['Resumen'];

        foreach (($this->reporte['resumen'] ?? []) as $key => $value) {
            $data[] = [$this->formatearEtiqueta($key), $value];
        }

        $data[] = [];
        $data[] = $this->reporte['columnas'] ?? [];

        foreach (($this->reporte['filas'] ?? []) as $fila) {
            $data[] = array_values($fila);
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $value = $sheet->getCell("A{$row}")->getValue();

            if (in_array($value, ['Filtros aplicados', 'Resumen'], true)) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
            }

            if ($value && $this->esFilaCabecera($sheet, $row)) {
                $sheet->getStyle("A{$row}:Z{$row}")->getFont()->setBold(true);
            }
        }

        return [];
    }

    private function esFilaCabecera(Worksheet $sheet, int $row): bool
    {
        $primerValor = $sheet->getCell("A{$row}")->getValue();

        return in_array($primerValor, [
            'Producto',
            'Fecha',
            'Nro factura',
            'Nro cotización',
        ], true);
    }

    private function formatearEtiqueta(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
