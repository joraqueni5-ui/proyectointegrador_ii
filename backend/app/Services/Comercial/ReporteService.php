<?php

namespace App\Services\Comercial;

use App\Exceptions\ReglaNegocioException;
use App\Models\Cotizacion;
use App\Models\Factura;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Producto;

class ReporteService
{
    public function generar(string $tipo, array $filtros = []): array
    {
        return match ($tipo) {
            'productos' => $this->productos($filtros),
            'inventario' => $this->inventarioActual($filtros),
            'movimientos' => $this->movimientos($filtros),
            'ventas' => $this->ventas($filtros),
            'cotizaciones' => $this->cotizaciones($filtros),
            'alertas_stock' => $this->alertasStock($filtros),
            default => throw new ReglaNegocioException('Tipo de reporte no válido.'),
        };
    }

    private function inventarioActual(array $filtros): array
    {
        $query = Inventario::with([
            'producto:id,codigo_producto,nombre_producto,categoria,unidad_medida,stock_minimo',
            'almacen:id,nombre_almacen,id_responsable',
            'almacen.responsable:id,name,paterno,materno',
        ]);

        $this->aplicarFiltrosInventario($query, $filtros);

        $items = $query->orderBy('ultima_actualizacion', 'desc')->get();

        $filas = $items->map(function ($item) {
            $stockActual = (float) $item->stock_actual;
            $stockMinimo = (float) $item->stock_minimo;

            $estado = match (true) {
                $stockActual <= 0 => 'Sin stock',
                $stockActual <= $stockMinimo => 'Bajo mínimo',
                default => 'Normal',
            };

            return [
                'producto' => $item->producto?->nombre_producto,
                'codigo' => $item->producto?->codigo_producto,
                'categoria' => $item->producto?->categoria,
                'almacen' => $item->almacen?->nombre_almacen,
                'responsable' => $this->nombreUsuario($item->almacen?->responsable),
                'stock_actual' => $stockActual,
                'stock_minimo' => $stockMinimo,
                'unidad' => $item->producto?->unidad_medida,
                'estado' => $estado,
                'ultima_actualizacion' => optional($item->ultima_actualizacion)->format('d/m/Y H:i'),
            ];
        })->values();

        return [
            'titulo' => 'Reporte de inventario actual',
            'tipo' => 'inventario',
            'resumen' => [
                'total_registros' => $filas->count(),
                'sin_stock' => $filas->where('estado', 'Sin stock')->count(),
                'bajo_minimo' => $filas->where('estado', 'Bajo mínimo')->count(),
                'normal' => $filas->where('estado', 'Normal')->count(),
            ],
            'columnas' => [
                'Producto',
                'Código',
                'Categoría',
                'Almacén',
                'Responsable',
                'Stock actual',
                'Stock mínimo',
                'Unidad',
                'Estado',
                'Última actualización',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function movimientos(array $filtros): array
    {
        $query = Movimiento::with([
            'producto:id,codigo_producto,nombre_producto,categoria,unidad_medida',
            'almacen:id,nombre_almacen,id_responsable',
            'almacen.responsable:id,name,paterno,materno',
            'usuario:id,name,paterno,materno',
            'factura:id,nro_factura',
        ]);

        $this->aplicarFiltroFechas($query, $filtros, 'fecha_movimiento');

        $query
            ->when($filtros['id_producto'] ?? null, fn ($q, $id) => $q->where('id_producto', $id))
            ->when($filtros['id_almacen'] ?? null, fn ($q, $id) => $q->where('id_almacen', $id))
            ->when($filtros['tipo_movimiento'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['motivo'] ?? null, fn ($q, $motivo) => $q->where('motivo', $motivo))
            ->when($filtros['id_usuario'] ?? null, fn ($q, $id) => $q->where('id_usuario', $id));

        $items = $query
            ->orderBy('fecha_movimiento', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $filas = $items->map(fn ($mov) => [
            'fecha' => optional($mov->fecha_movimiento)->format('d/m/Y'),
            'producto' => $mov->producto?->nombre_producto,
            'categoria' => $mov->producto?->categoria,
            'almacen' => $mov->almacen?->nombre_almacen,
            'responsable_almacen' => $this->nombreUsuario($mov->almacen?->responsable),
            'tipo' => $mov->tipo,
            'cantidad' => (float) $mov->cantidad,
            'unidad' => $mov->producto?->unidad_medida,
            'motivo' => $mov->motivo,
            'stock_anterior' => (float) $mov->stock_anterior,
            'stock_posterior' => (float) $mov->stock_posterior,
            'registrado_por' => $this->nombreUsuario($mov->usuario),
            'factura' => $mov->factura?->nro_factura,
        ])->values();

        return [
            'titulo' => 'Reporte de movimientos de inventario',
            'tipo' => 'movimientos',
            'resumen' => [
                'total_movimientos' => $filas->count(),
                'entradas' => $filas->where('tipo', 'Entrada')->count(),
                'salidas' => $filas->where('tipo', 'Salida')->count(),
                'cantidad_total' => $filas->sum('cantidad'),
            ],
            'columnas' => [
                'Fecha',
                'Producto',
                'Categoría',
                'Almacén',
                'Responsable almacén',
                'Tipo',
                'Cantidad',
                'Unidad',
                'Motivo',
                'Stock anterior',
                'Stock posterior',
                'Registrado por',
                'Factura',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function ventas(array $filtros): array
    {
        $query = Factura::with([
            'productor:id,nombre_completo,telefono',
            'usuario:id,name,paterno,materno',
        ]);

        $this->aplicarFiltroFechas($query, $filtros, 'fecha_emision');

        $query
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['metodo_pago'] ?? null, fn ($q, $metodo) => $q->where('metodo_pago', $metodo))
            ->when($filtros['id_productor'] ?? null, fn ($q, $id) => $q->where('id_productor', $id))
            ->when($filtros['id_usuario'] ?? null, fn ($q, $id) => $q->where('id_usuario', $id))
            ->when($filtros['monto_min'] ?? null, fn ($q, $monto) => $q->where('monto_total', '>=', $monto))
            ->when($filtros['monto_max'] ?? null, fn ($q, $monto) => $q->where('monto_total', '<=', $monto));

        $items = $query->orderBy('fecha_emision', 'desc')->get();

        $filas = $items->map(fn ($venta) => [
            'nro_factura' => $venta->nro_factura,
            'productor' => $venta->productor?->nombre_completo,
            'telefono' => $venta->productor?->telefono,
            'fecha' => optional($venta->fecha_emision)->format('d/m/Y'),
            'metodo_pago' => $venta->metodo_pago,
            'estado' => $venta->estado,
            'subtotal' => (float) $venta->subtotal,
            'descuento' => (float) $venta->descuento,
            'total' => (float) $venta->monto_total,
            'registrado_por' => $this->nombreUsuario($venta->usuario),
        ])->values();

        return [
            'titulo' => 'Reporte de ventas',
            'tipo' => 'ventas',
            'resumen' => [
                'total_facturas' => $filas->count(),
                'total_vendido' => $filas->where('estado', '!=', 'Anulada')->sum('total'),
                'pagadas' => $filas->where('estado', 'Pagada')->count(),
                'pendientes' => $filas->where('estado', 'Pendiente')->count(),
                'anuladas' => $filas->where('estado', 'Anulada')->count(),
                'total_pendiente' => $filas->where('estado', 'Pendiente')->sum('total'),
            ],
            'columnas' => [
                'Nro factura',
                'Productor',
                'Teléfono',
                'Fecha',
                'Método pago',
                'Estado',
                'Subtotal',
                'Descuento',
                'Total',
                'Registrado por',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function cotizaciones(array $filtros): array
    {
        $query = Cotizacion::with([
            'productor:id,nombre_completo,telefono',
            'usuario:id,name,paterno,materno',
            'aprobador:id,name,paterno,materno',
            'factura:id,nro_factura',
        ]);

        $this->aplicarFiltroFechas($query, $filtros, 'fecha_emision');

        $query
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['metodo_pago'] ?? null, fn ($q, $metodo) => $q->where('metodo_pago', $metodo))
            ->when($filtros['id_productor'] ?? null, fn ($q, $id) => $q->where('id_productor', $id))
            ->when($filtros['id_usuario'] ?? null, fn ($q, $id) => $q->where('id_usuario', $id))
            ->when($filtros['id_aprobador'] ?? null, fn ($q, $id) => $q->where('id_aprobador', $id));

        $items = $query->orderBy('fecha_emision', 'desc')->get();

        $filas = $items->map(fn ($cot) => [
            'nro_cotizacion' => $cot->nro_cotizacion,
            'productor' => $cot->productor?->nombre_completo,
            'telefono' => $cot->productor?->telefono,
            'fecha' => optional($cot->fecha_emision)->format('d/m/Y'),
            'metodo_pago' => $cot->metodo_pago,
            'estado' => $cot->estado,
            'subtotal' => (float) $cot->subtotal,
            'descuento' => (float) $cot->descuento,
            'total' => (float) $cot->monto_total,
            'creado_por' => $this->nombreUsuario($cot->usuario),
            'aprobado_rechazado_por' => $this->nombreUsuario($cot->aprobador),
            'factura_generada' => $cot->factura?->nro_factura,
        ])->values();

        return [
            'titulo' => 'Reporte de cotizaciones',
            'tipo' => 'cotizaciones',
            'resumen' => [
                'total_cotizaciones' => $filas->count(),
                'total_cotizado' => $filas->whereNotIn('estado', ['Anulada', 'Rechazada'])->sum('total'),
                'pendientes' => $filas->where('estado', 'Pendiente')->count(),
                'aprobadas' => $filas->where('estado', 'Aprobada')->count(),
                'convertidas' => $filas->where('estado', 'Convertida')->count(),
                'rechazadas' => $filas->where('estado', 'Rechazada')->count(),
            ],
            'columnas' => [
                'Nro cotización',
                'Productor',
                'Teléfono',
                'Fecha',
                'Método pago',
                'Estado',
                'Subtotal',
                'Descuento',
                'Total',
                'Creado por',
                'Aprobado/Rechazado por',
                'Factura generada',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function alertasStock(array $filtros): array
    {
        $inventario = $this->inventarioActual($filtros);

        $filas = collect($inventario['filas'])
            ->filter(fn ($fila) => in_array($fila['estado'], ['Sin stock', 'Bajo mínimo']))
            ->values();

        return [
            'titulo' => 'Reporte de alertas de stock',
            'tipo' => 'alertas_stock',
            'resumen' => [
                'total_alertas' => $filas->count(),
                'sin_stock' => $filas->where('estado', 'Sin stock')->count(),
                'bajo_minimo' => $filas->where('estado', 'Bajo mínimo')->count(),
            ],
            'columnas' => [
                'Producto',
                'Código',
                'Categoría',
                'Almacén',
                'Responsable',
                'Stock actual',
                'Stock mínimo',
                'Unidad',
                'Estado',
                'Última actualización',
            ],
            'filas' => $filas->toArray(),
        ];
    }

    private function aplicarFiltroFechas($query, array $filtros, string $campo): void
    {
        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate($campo, '>=', $filtros['fecha_inicio']);
        }

        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate($campo, '<=', $filtros['fecha_fin']);
        }
    }

    private function aplicarFiltrosInventario($query, array $filtros): void
    {
        $query
            ->when($filtros['id_almacen'] ?? null, fn ($q, $id) => $q->where('id_almacen', $id))
            ->when($filtros['id_producto'] ?? null, fn ($q, $id) => $q->where('id_producto', $id))
            ->when($filtros['id_responsable'] ?? null, function ($q, $id) {
                $q->whereHas('almacen', fn ($a) => $a->where('id_responsable', $id));
            })
            ->when($filtros['categoria'] ?? null, function ($q, $categoria) {
                $q->whereHas('producto', fn ($p) => $p->where('categoria', $categoria));
            });
    }

    private function nombreUsuario($usuario): string
    {
        if (! $usuario) {
            return '—';
        }

        return trim(implode(' ', array_filter([
            $usuario->name ?? null,
            $usuario->paterno ?? null,
            $usuario->materno ?? null,
        ]))) ?: '—';
    }

    private function productos(array $filtros): array
    {
        $query = Producto::with([
            'inventarios',
            'usuarioRegistro:id,name,paterno,materno',
            'usuarioActualizacion:id,name,paterno,materno',
        ]);

        $query
            ->when($filtros['categoria'] ?? null, fn ($q, $categoria) => $q->where('categoria', $categoria))
            ->when(isset($filtros['estado']) && $filtros['estado'] !== '', function ($q) use ($filtros) {
                $activo = $filtros['estado'] === 'Activo';
                $q->where('activo', $activo);
            })
            ->when($filtros['busqueda'] ?? null, function ($q, $busqueda) {
                $q->where(function ($sub) use ($busqueda) {
                    $sub->where('nombre_producto', 'ILIKE', "%{$busqueda}%")
                        ->orWhere('codigo_producto', 'ILIKE', "%{$busqueda}%");
                });
            });

        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_inicio']);
        }

        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_fin']);
        }

        if (! empty($filtros['id_usuario'])) {
            $query->where('id_usuario_registro', $filtros['id_usuario']);
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $filas = $items->map(function ($producto) {
            $stockTotal = $producto->inventarios->sum(
                fn ($inventario) => (float) $inventario->stock_actual
            );

            return [
                'codigo' => $producto->codigo_producto,
                'producto' => $producto->nombre_producto,
                'categoria' => $producto->categoria,
                'unidad' => $producto->unidad_medida,
                'precio_unitario' => (float) $producto->precio_unitario,
                'stock_total' => $stockTotal,
                'estado' => $producto->activo ? 'Activo' : 'Inactivo',
                'registrado_por' => $this->nombreUsuario($producto->usuarioRegistro),
                'actualizado_por' => $this->nombreUsuario($producto->usuarioActualizacion),
                'fecha_registro' => optional($producto->created_at)->format('d/m/Y H:i'),
            ];
        })->values();

        return [
            'titulo' => 'Reporte de productos',
            'tipo' => 'productos',
            'resumen' => [
                'total_productos' => $filas->count(),
                'activos' => $filas->where('estado', 'Activo')->count(),
                'inactivos' => $filas->where('estado', 'Inactivo')->count(),
                'stock_total' => $filas->sum('stock_total'),
            ],
            'columnas' => [
                'Código',
                'Producto',
                'Categoría',
                'Unidad',
                'Precio unitario',
                'Stock total',
                'Estado',
                'Registrado por',
                'Actualizado por',
                'Fecha registro',
            ],
            'filas' => $filas->toArray(),
        ];
    }
}
