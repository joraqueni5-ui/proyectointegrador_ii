<?php

namespace App\Services\Comercial;

use App\DTOs\Comercial\FacturaDTO;
use App\DTOs\Comercial\MovimientoDTO;
use App\Exceptions\ReglaNegocioException;
use App\Models\DetalleFactura;
use App\Models\Factura;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VentaService
{
    public function __construct(
        private readonly MovimientoService $movimientoService
    ) {}

    public function dashboard(): array
    {
        $mesActual = now()->month;
        $anio = now()->year;

        $facturasMes = Factura::whereMonth('fecha_emision', $mesActual)
            ->whereYear('fecha_emision', $anio)
            ->where('estado', '!=', 'Anulada');

        $ventasMes = (clone $facturasMes)->sum('monto_total');
        $facturasMesCnt = (clone $facturasMes)->count();

        $saldosPendientes = Factura::where('estado', 'Pendiente')
            ->sum('monto_total');

        $productoresPendientes = Factura::where('estado', 'Pendiente')
            ->distinct('id_productor')
            ->count('id_productor');

        $facturasVencidas = Factura::where('estado', 'Pendiente')
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->count();

        return [
            'ventas_mes' => (float) $ventasMes,
            'facturas_emitidas' => $facturasMesCnt,
            'saldos_pendientes' => (float) $saldosPendientes,
            'productores_pend' => $productoresPendientes,
            'facturas_vencidas' => $facturasVencidas,
        ];
    }

    public function listar(
        string $busqueda = '',
        ?string $estado = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Factura::with([
            'productor:id,nombre_completo,telefono',
            'usuario:id,name,paterno,materno',
            'detalles.producto:id,codigo_producto,nombre_producto,categoria,unidad_medida',
        ])
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($sub) use ($busqueda) {
                    $sub->where('nro_factura', 'ILIKE', "%{$busqueda}%")
                        ->orWhereHas('productor', function ($p) use ($busqueda) {
                            $p->where('nombre_completo', 'ILIKE', "%{$busqueda}%");
                        });
                });
            })
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(fn ($f) => tap($f, fn ($x) => $x->detalles->each(fn ($d) => $d->producto?->makeHidden('stock_total'))));
    }

    public function ver(Factura $factura): Factura
    {
        return $factura->load([
            'productor:id,nombre_completo,telefono',
            'usuario:id,name,paterno,materno',
            'detalles.producto:id,codigo_producto,nombre_producto,categoria,unidad_medida',
            'movimientos.almacen:id,nombre_almacen',
        ]);
    }

    public function crear(FacturaDTO $dto): Factura
    {
        return DB::transaction(function () use ($dto) {
            $subtotal = collect($dto->detalles)->sum(function ($detalle) {
                $cantidad = (float) $detalle['cantidad'];
                $precio = (float) $detalle['precio_unitario'];
                $descuentoLinea = (float) ($detalle['descuento_linea'] ?? 0);

                return max(($cantidad * $precio) - $descuentoLinea, 0);
            });

            $descuento = (float) $dto->descuento;
            $montoTotal = max($subtotal - $descuento, 0);

            $facturaData = $dto->toArray();
            $facturaData['subtotal'] = $subtotal;
            $facturaData['descuento'] = $descuento;
            $facturaData['monto_total'] = $montoTotal;
            $facturaData['nro_factura'] = $this->generarNroFactura();

            $factura = Factura::create($facturaData);

            foreach ($dto->detalles as $detalle) {
                $cantidad = (float) $detalle['cantidad'];
                $precio = (float) $detalle['precio_unitario'];
                $descuentoLinea = (float) ($detalle['descuento_linea'] ?? 0);
                $subtotalLinea = max(($cantidad * $precio) - $descuentoLinea, 0);

                DetalleFactura::create([
                    'id_factura' => $factura->id,
                    'id_producto' => $detalle['id_producto'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'descuento_linea' => $descuentoLinea,
                    'subtotal' => $subtotalLinea,
                ]);

                $movimientoDTO = MovimientoDTO::fromRequest([
                    'id_producto' => $detalle['id_producto'],
                    'id_almacen' => $detalle['id_almacen'],
                    'id_factura' => $factura->id,
                    'id_usuario' => $dto->id_usuario,
                    'tipo' => 'Salida',
                    'cantidad' => $cantidad,
                    'motivo' => 'Venta',
                    'fecha_movimiento' => $dto->fecha_emision,
                    'observaciones' => "Venta factura #{$factura->nro_factura}",
                ]);

                $this->movimientoService->registrar($movimientoDTO);
            }

            return $this->ver($factura);
        });
    }

    public function cambiarEstado(Factura $factura, string $estado): Factura
    {
        return DB::transaction(function () use ($factura, $estado) {
            $factura = Factura::whereKey($factura->id)->lockForUpdate()->firstOrFail();

            if ($factura->estado === 'Anulada') {
                throw new ReglaNegocioException('No se puede cambiar el estado de una venta anulada.');
            }

            // Pendiente -> Parcial -> Pagada. Una venta pagada no vuelve atrás (se anula si hay un error).
            $permitidas = [
                'Pendiente' => ['Parcial', 'Pagada'],
                'Parcial' => ['Pendiente', 'Pagada'],
                'Pagada' => [],
            ];

            if ($estado !== $factura->estado && ! in_array($estado, $permitidas[$factura->estado] ?? [], true)) {
                throw new ReglaNegocioException(
                    "No se puede cambiar una venta de {$factura->estado} a {$estado}."
                    .($factura->estado === 'Pagada' ? ' Si hubo un error, anula la venta.' : '')
                );
            }

            $factura->update(['estado' => $estado]);

            return $this->ver($factura->fresh());
        });
    }

    public function anular(Factura $factura, int $idUsuario): Factura
    {
        return DB::transaction(function () use ($factura, $idUsuario) {
            // Bloqueo para que dos anulaciones simultáneas no repongan el stock dos veces.
            $factura = Factura::whereKey($factura->id)->lockForUpdate()->firstOrFail();

            if ($factura->estado === 'Anulada') {
                throw new ReglaNegocioException('La venta ya se encuentra anulada.');
            }

            $salidas = $factura->movimientos()->where('tipo', 'Salida')->get();

            if ($salidas->isEmpty()) {
                throw new ReglaNegocioException('No se encontraron los movimientos de salida para anular la venta.');
            }

            // Cada salida se revierte en su mismo almacén y por la misma cantidad.
            foreach ($salidas as $salida) {
                $this->movimientoService->registrar(MovimientoDTO::fromRequest([
                    'id_producto' => $salida->id_producto,
                    'id_almacen' => $salida->id_almacen,
                    'id_factura' => $factura->id,
                    'id_usuario' => $idUsuario,
                    'tipo' => 'Entrada',
                    'cantidad' => (float) $salida->cantidad,
                    'motivo' => 'Devolucion',
                    'fecha_movimiento' => now()->toDateString(),
                    'observaciones' => "Anulación de factura #{$factura->nro_factura}",
                ]));
            }

            $factura->update([
                'estado' => 'Anulada',
            ]);

            return $this->ver($factura->fresh());
        });
    }

    public function tendenciaMensual(int $meses = 6): array
    {
        return Factura::selectRaw(
            "TO_CHAR(fecha_emision, 'YYYY-MM') as mes, SUM(monto_total) as total, COUNT(*) as cantidad"
        )
            ->where('estado', '!=', 'Anulada')
            ->where('fecha_emision', '>=', now()->subMonths($meses)->toDateString())
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->toArray();
    }

    private function generarNroFactura(): string
    {
        $ultimo = Factura::query()
            ->orderBy('id', 'desc')
            ->value('nro_factura');

        $numero = $ultimo ? ((int) $ultimo + 1) : 1;

        return str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
