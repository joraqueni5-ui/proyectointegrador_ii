<?php

namespace App\Services\Comercial;

use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventarioService
{
    public function dashboard(): array
    {
        $inventarios = Inventario::with('producto', 'almacen')->get();

        $movimientosMes = Movimiento::whereMonth('fecha_movimiento', now()->month)
            ->whereYear('fecha_movimiento', now()->year)
            ->count();

        $semillas = $inventarios->filter(
            fn ($i) => $i->producto && $i->producto->categoria === 'Semilla'
        );

        $bioinsumos = $inventarios->filter(
            fn ($i) => $i->producto && $i->producto->categoria === 'Bioinsumo'
        );

        $alertas = count($this->alertasStock());

        return [
            'kpis' => [
                'stock_semillas_kg' => $semillas->sum('stock_actual'),
                'total_bioinsumos' => $bioinsumos->count(),
                'alertas_stock' => $alertas,
                'movimientos_mes' => $movimientosMes,
            ],
            'semillas' => $semillas->map(fn ($i) => $this->formatInventario($i))->values(),
            'bioinsumos' => $bioinsumos->map(fn ($i) => $this->formatInventario($i))->values(),
        ];
    }

    public function listarInventario(int $perPage = 20): LengthAwarePaginator
    {
        return Inventario::with([
            'producto:id,codigo_producto,nombre_producto,categoria,unidad_medida,stock_minimo',
            'almacen:id,nombre_almacen',
        ])
            ->orderBy('ultima_actualizacion', 'desc')
            ->paginate($perPage);
    }

    public function alertasStock(): array
    {
        $alertas = collect();

        $inventarios = Inventario::with([
            'producto:id,codigo_producto,nombre_producto,categoria,unidad_medida,stock_minimo,activo',
            'almacen:id,nombre_almacen',
        ])
            ->whereHas('producto', function ($q) {
                $q->where('activo', true);
            })
            ->get();

        foreach ($inventarios as $inventario) {
            $stockActual = (float) $inventario->stock_actual;
            $stockMinimo = (float) $inventario->stock_minimo;

            $debeAlertar = match (true) {
                $stockActual <= 0 => true,
                $stockMinimo > 0 && $stockActual <= $stockMinimo => true,
                $stockMinimo > 0 && $stockActual <= ($stockMinimo * 1.3) => true,
                default => false,
            };

            if ($debeAlertar) {
                $alertas->push($this->formatInventario($inventario));
            }
        }

        $productosSinInventario = Producto::query()
            ->where('activo', true)
            ->doesntHave('inventarios')
            ->get();

        foreach ($productosSinInventario as $producto) {
            $alertas->push([
                'id' => 'producto-'.$producto->id,
                'producto' => [
                    'id' => $producto->id,
                    'codigo_producto' => $producto->codigo_producto,
                    'nombre_producto' => $producto->nombre_producto,
                    'categoria' => $producto->categoria,
                    'unidad_medida' => $producto->unidad_medida,
                    'stock_minimo' => (float) ($producto->stock_minimo ?? 0),
                ],
                'almacen' => null,
                'stock_actual' => 0,
                'stock_minimo' => (float) ($producto->stock_minimo ?? 0),
                'stock_maximo' => null,
                'porcentaje' => 0,
                'estado' => 'sin_stock',
                'ultima_actualizacion' => null,
            ]);
        }

        return $alertas->values()->toArray();
    }

    private function formatInventario(Inventario $i): array
    {
        $stockMaximo = (float) ($i->stock_maximo ?? 0);

        $porcentaje = $stockMaximo > 0
            ? round(((float) $i->stock_actual / $stockMaximo) * 100, 1)
            : 0;

        $estado = match (true) {
            (float) $i->stock_actual <= 0 => 'sin_stock',
            (float) $i->stock_actual <= (float) $i->stock_minimo => 'bajo_minimo',
            (float) $i->stock_actual <= ((float) $i->stock_minimo * 1.3) => 'alerta',
            default => 'normal',
        };

        return [
            'id' => $i->id,
            'producto' => $i->producto,
            'almacen' => $i->almacen,
            'stock_actual' => (float) $i->stock_actual,
            'stock_minimo' => (float) $i->stock_minimo,
            'stock_maximo' => $i->stock_maximo ? (float) $i->stock_maximo : null,
            'porcentaje' => $porcentaje,
            'estado' => $estado,
            'ultima_actualizacion' => $i->ultima_actualizacion,
        ];
    }
}
