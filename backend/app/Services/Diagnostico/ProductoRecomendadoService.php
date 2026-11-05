<?php

namespace App\Services\Diagnostico;

use App\DTOs\Diagnostico\ProductoRecomendadoDTO;
use App\Models\Enfermedad;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class ProductoRecomendadoService
{
    public function listar(Enfermedad $enfermedad)
    {
        return $enfermedad->productosRecomendados()
            ->with(['inventarios.almacen'])
            ->orderByRaw("CASE enfermedad_producto_recomendados.prioridad WHEN 'Alta' THEN 1 WHEN 'Media' THEN 2 ELSE 3 END")
            ->get();
    }

    public function vincular(
        Enfermedad $enfermedad,
        ProductoRecomendadoDTO $dto
    ) {
        return DB::transaction(function () use ($enfermedad, $dto) {
            $producto = Producto::findOrFail($dto->id_producto);

            if (! $producto->activo) {
                abort(422, 'No se puede vincular un producto inactivo.');
            }

            $pivotExistente = DB::table('enfermedad_producto_recomendados')
                ->where('id_enfermedad', $enfermedad->id)
                ->where('id_producto', $producto->id)
                ->first();

            if ($pivotExistente) {
                DB::table('enfermedad_producto_recomendados')
                    ->where('id_enfermedad', $enfermedad->id)
                    ->where('id_producto', $producto->id)
                    ->update([
                        ...$dto->toPivotArray(),
                        'activo' => true,
                        'updated_at' => now(),
                    ]);
            } else {
                $enfermedad->productosRecomendados()->attach(
                    $producto->id,
                    [
                        ...$dto->toPivotArray(),
                        'activo' => true,
                        'created_at' => now(),
                    ]
                );
            }

            return $enfermedad->fresh()->load([
                'productosRecomendados.inventarios.almacen',
            ]);
        });
    }

    public function actualizar(
        Enfermedad $enfermedad,
        Producto $producto,
        array $data
    ) {
        return DB::transaction(function () use ($enfermedad, $producto, $data) {
            $existe = DB::table('enfermedad_producto_recomendados')
                ->where('id_enfermedad', $enfermedad->id)
                ->where('id_producto', $producto->id)
                ->exists();

            if (! $existe) {
                abort(404, 'El producto no está vinculado a esta enfermedad.');
            }

            DB::table('enfermedad_producto_recomendados')
                ->where('id_enfermedad', $enfermedad->id)
                ->where('id_producto', $producto->id)
                ->update([
                    ...$data,
                    'updated_at' => now(),
                ]);

            return $enfermedad->fresh()->load([
                'productosRecomendados.inventarios.almacen',
            ]);
        });
    }

    public function desactivar(Enfermedad $enfermedad, Producto $producto)
    {
        return $this->actualizar($enfermedad, $producto, [
            'activo' => false,
        ]);
    }

    public function eliminar(Enfermedad $enfermedad, Producto $producto): void
    {
        DB::table('enfermedad_producto_recomendados')
            ->where('id_enfermedad', $enfermedad->id)
            ->where('id_producto', $producto->id)
            ->delete();
    }
}
