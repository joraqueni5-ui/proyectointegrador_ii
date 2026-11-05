<?php

namespace App\Services\Comercial;

use App\DTOs\Comercial\MovimientoDTO;
use App\Exceptions\ReglaNegocioException;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Support\Paginacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MovimientoService
{
    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $idProducto = $filtros['id_producto'] ?? null;
        $idAlmacen = $filtros['id_almacen'] ?? null;
        $tipo = $filtros['tipo'] ?? null;
        $perPage = Paginacion::porPagina($filtros['per_page'] ?? null, 20);

        return Movimiento::with([
            'producto:id,codigo_producto,nombre_producto,categoria,unidad_medida',
            'almacen:id,nombre_almacen',
            'usuario:id,name,paterno,materno',
            'factura:id,nro_factura',
        ])
            ->when($idProducto, fn ($q) => $q->where('id_producto', $idProducto))
            ->when($idAlmacen, fn ($q) => $q->where('id_almacen', $idAlmacen))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('fecha_movimiento', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function registrar(MovimientoDTO $dto): Movimiento
    {
        return DB::transaction(function () use ($dto) {
            $producto = Producto::findOrFail($dto->id_producto);
            $almacen = Almacen::findOrFail($dto->id_almacen);

            if (! $producto->activo) {
                throw new ReglaNegocioException('No se puede registrar movimiento para un producto inactivo.');
            }

            if ($almacen->estado !== 'Activo') {
                throw new ReglaNegocioException('No se puede registrar movimiento en un almacén inactivo.');
            }

            $this->validarMotivoSegunTipo($dto->tipo, $dto->motivo);

            $clave = [
                'id_producto' => $dto->id_producto,
                'id_almacen' => $dto->id_almacen,
            ];

            // Se bloquea la fila para que dos operaciones simultáneas
            // no lean el mismo stock y lo dejen negativo.
            $inventario = Inventario::where($clave)->lockForUpdate()->first()
                ?? Inventario::create([
                    ...$clave,
                    'stock_actual' => 0,
                    'stock_minimo' => $producto->stock_minimo ?? 0,
                    'stock_maximo' => 9999,
                    'ultima_actualizacion' => now(),
                ]);

            $stockAnterior = (float) $inventario->stock_actual;

            $stockPosterior = match ($dto->tipo) {
                'Entrada' => $stockAnterior + $dto->cantidad,
                'Salida' => $stockAnterior - $dto->cantidad,
                default => $stockAnterior,
            };

            if ($stockPosterior < 0) {
                throw new ReglaNegocioException(
                    "Stock insuficiente. Stock actual: {$stockAnterior} {$producto->unidad_medida}"
                );
            }

            $inventario->update([
                'stock_actual' => $stockPosterior,
                'ultima_actualizacion' => now(),
            ]);

            return Movimiento::create([
                ...$dto->toArray(),
                'stock_anterior' => $stockAnterior,
                'stock_posterior' => $stockPosterior,
            ])->load([
                'producto:id,codigo_producto,nombre_producto,categoria,unidad_medida',
                'almacen:id,nombre_almacen',
                'usuario:id,name,paterno,materno',
                'factura:id,nro_factura',
            ]);
        });
    }

    private function validarMotivoSegunTipo(string $tipo, string $motivo): void
    {
        $motivosEntrada = [
            'Compra',
            'Devolucion',
            'Ajuste_entrada',
            'Traslado_entrada',
            'Otro',
        ];

        $motivosSalida = [
            'Venta',
            'Ajuste_salida',
            'Traslado_salida',
            'Otro',
        ];

        if ($tipo === 'Entrada' && ! in_array($motivo, $motivosEntrada, true)) {
            throw new ReglaNegocioException('El motivo seleccionado no corresponde a un movimiento de entrada.');
        }

        if ($tipo === 'Salida' && ! in_array($motivo, $motivosSalida, true)) {
            throw new ReglaNegocioException('El motivo seleccionado no corresponde a un movimiento de salida.');
        }
    }
}
