<?php

namespace App\Services\Comercial;

use App\DTOs\Comercial\CotizacionDTO;
use App\DTOs\Comercial\FacturaDTO;
use App\Exceptions\ReglaNegocioException;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Inventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    public function __construct(
        private readonly VentaService $ventaService
    ) {}

    public function listar(
        string $busqueda = '',
        ?string $estado = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Cotizacion::with([
            'productor:id,nombre_completo,telefono',
            'usuario:id,name,paterno,materno',
            'aprobador:id,name,paterno,materno',
            'factura:id,nro_factura',
            'detalles.producto:id,codigo_producto,nombre_producto,categoria,unidad_medida',
            'detalles.almacen:id,nombre_almacen',
        ])
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($sub) use ($busqueda) {
                    $sub->where('nro_cotizacion', 'ILIKE', "%{$busqueda}%")
                        ->orWhereHas('productor', function ($p) use ($busqueda) {
                            $p->where('nombre_completo', 'ILIKE', "%{$busqueda}%");
                        });
                });
            })
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function ver(Cotizacion $cotizacion): Cotizacion
    {
        return $cotizacion->load([
            'productor:id,nombre_completo,telefono',
            'usuario:id,name,paterno,materno',
            'aprobador:id,name,paterno,materno',
            'factura:id,nro_factura,monto_total,estado',
            'detalles.producto:id,codigo_producto,nombre_producto,categoria,unidad_medida,precio_unitario',
            'detalles.almacen:id,nombre_almacen',
        ]);
    }

    public function crear(CotizacionDTO $dto): Cotizacion
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

            $cotizacion = Cotizacion::create([
                'nro_cotizacion' => $this->generarNroCotizacion(),
                'id_productor' => $dto->id_productor,
                'id_usuario' => $dto->id_usuario,
                'fecha_emision' => $dto->fecha_emision,
                'fecha_vencimiento' => $dto->fecha_vencimiento,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'monto_total' => $montoTotal,
                'metodo_pago' => $dto->metodo_pago,
                'estado' => 'Pendiente',
                'observaciones' => $dto->observaciones,
            ]);

            foreach ($dto->detalles as $detalle) {
                $cantidad = (float) $detalle['cantidad'];
                $precio = (float) $detalle['precio_unitario'];
                $descuentoLinea = (float) ($detalle['descuento_linea'] ?? 0);
                $subtotalLinea = max(($cantidad * $precio) - $descuentoLinea, 0);

                DetalleCotizacion::create([
                    'id_cotizacion' => $cotizacion->id,
                    'id_producto' => $detalle['id_producto'],
                    'id_almacen' => $detalle['id_almacen'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'descuento_linea' => $descuentoLinea,
                    'subtotal' => $subtotalLinea,
                ]);
            }

            return $this->ver($cotizacion);
        });
    }

    public function aprobar(Cotizacion $cotizacion, int $idAprobador): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $idAprobador) {
            $cotizacion = $this->bloquear($cotizacion, 'Pendiente', 'Solo se pueden aprobar cotizaciones pendientes.');
            $this->validarVigencia($cotizacion);

            $cotizacion->load('detalles.producto');

            // Se suma por producto y almacén: dos líneas iguales no deben pasar
            // la validación por separado si juntas superan el stock.
            $porUbicacion = $cotizacion->detalles->groupBy(fn ($d) => "{$d->id_producto}-{$d->id_almacen}");

            foreach ($porUbicacion as $lineas) {
                $detalle = $lineas->first();
                $cantidad = $lineas->sum(fn ($d) => (float) $d->cantidad);
                $stockDisponible = $this->stockDisponible($detalle->id_producto, $detalle->id_almacen);

                if ($cantidad > $stockDisponible) {
                    throw new ReglaNegocioException(
                        "Stock insuficiente para {$detalle->producto->nombre_producto}. ".
                            "Disponible: {$stockDisponible} {$detalle->producto->unidad_medida}."
                    );
                }
            }

            $cotizacion->update([
                'estado' => 'Aprobada',
                'id_aprobador' => $idAprobador,
                'aprobada_at' => now(),
            ]);

            return $this->ver($cotizacion->fresh());
        });
    }

    public function rechazar(Cotizacion $cotizacion, int $idAprobador, string $motivo): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $idAprobador, $motivo) {
            $cotizacion = $this->bloquear($cotizacion, 'Pendiente', 'Solo se pueden rechazar cotizaciones pendientes.');

            $cotizacion->update([
                'estado' => 'Rechazada',
                'id_aprobador' => $idAprobador,
                'motivo_rechazo' => $motivo,
                'rechazada_at' => now(),
            ]);

            return $this->ver($cotizacion->fresh());
        });
    }

    public function anular(Cotizacion $cotizacion): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion) {
            $cotizacion = Cotizacion::whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();

            if (in_array($cotizacion->estado, ['Convertida', 'Anulada'], true)) {
                throw new ReglaNegocioException('No se puede anular esta cotización.');
            }

            $cotizacion->update(['estado' => 'Anulada']);

            return $this->ver($cotizacion->fresh());
        });
    }

    public function convertirVenta(Cotizacion $cotizacion, int $idUsuario): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $idUsuario) {
            // El bloqueo evita que un doble clic genere dos ventas.
            $cotizacion = $this->bloquear($cotizacion, 'Aprobada', 'Solo se pueden convertir cotizaciones aprobadas.');
            $this->validarVigencia($cotizacion);

            $cotizacion->load('detalles');

            $detalles = $cotizacion->detalles->map(function ($detalle) {
                return [
                    'id_producto' => $detalle->id_producto,
                    'id_almacen' => $detalle->id_almacen,
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'descuento_linea' => (float) $detalle->descuento_linea,
                ];
            })->toArray();

            $estadoVenta = $cotizacion->metodo_pago === 'Credito'
                ? 'Pendiente'
                : 'Pagada';

            $facturaDTO = FacturaDTO::fromRequest([
                'id_productor' => $cotizacion->id_productor,
                'id_usuario' => $idUsuario,
                'tipo' => 'Venta',
                'metodo_pago' => $cotizacion->metodo_pago,
                'fecha_emision' => now()->toDateString(),
                'fecha_vencimiento' => $cotizacion->fecha_vencimiento?->toDateString(),
                'descuento' => (float) $cotizacion->descuento,
                'observaciones' => "Venta generada desde cotización #{$cotizacion->nro_cotizacion}",
                'estado' => $estadoVenta,
                'detalles' => $detalles,
            ]);

            $factura = $this->ventaService->crear($facturaDTO);

            $cotizacion->update([
                'estado' => 'Convertida',
                'convertida_factura_id' => $factura->id,
                'convertida_at' => now(),
            ]);

            return $this->ver($cotizacion->fresh());
        });
    }

    private function bloquear(Cotizacion $cotizacion, string $estadoRequerido, string $mensaje): Cotizacion
    {
        $cotizacion = Cotizacion::whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();

        if ($cotizacion->estado !== $estadoRequerido) {
            throw new ReglaNegocioException($mensaje);
        }

        return $cotizacion;
    }

    private function validarVigencia(Cotizacion $cotizacion): void
    {
        if ($cotizacion->fecha_vencimiento && $cotizacion->fecha_vencimiento->lt(today())) {
            throw new ReglaNegocioException('La cotización está vencida. Registre una nueva cotización con precios actualizados.');
        }
    }

    private function stockDisponible(int $idProducto, int $idAlmacen): float
    {
        return (float) Inventario::where('id_producto', $idProducto)
            ->where('id_almacen', $idAlmacen)
            ->value('stock_actual');
    }

    private function generarNroCotizacion(): string
    {
        $ultimo = Cotizacion::query()
            ->orderBy('id', 'desc')
            ->value('nro_cotizacion');

        if (! $ultimo) {
            return 'COT-0001';
        }

        $numero = (int) str_replace('COT-', '', $ultimo);
        $numero++;

        return 'COT-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
