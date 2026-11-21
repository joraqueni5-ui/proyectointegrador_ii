<?php

namespace App\Services\Comercial;

use App\DTOs\Comercial\ProductoDTO;
use App\Models\Producto;
use App\Support\Paginacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductoService
{
    private array $relations = [
        'inventarios:id,id_producto,id_almacen,stock_actual',
    ];

    public function listar(array $filtros = []): Collection|LengthAwarePaginator
    {
        $busqueda = $filtros['search']
            ?? $filtros['buscar']
            ?? $filtros['busqueda']
            ?? null;

        $query = Producto::query()
            ->with($this->relations)
            ->when(isset($filtros['activo']), function ($query) use ($filtros) {
                $query->where(
                    'activo',
                    filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN)
                );
            })
            ->when($filtros['categoria'] ?? null, function ($query, $categoria) {
                $query->where('categoria', $categoria);
            })
            ->when($busqueda, function ($query, $busqueda) {
                $query->where(function ($sub) use ($busqueda) {
                    $sub->where('codigo_producto', 'ILIKE', "%{$busqueda}%")
                        ->orWhere('nombre_producto', 'ILIKE', "%{$busqueda}%")
                        ->orWhere('categoria', 'ILIKE', "%{$busqueda}%");
                });
            })
            ->orderBy('nombre_producto');

        if (($filtros['todos'] ?? false) || ! isset($filtros['per_page'])) {
            return $query->get();
        }

        return $query->paginate(Paginacion::porPagina($filtros['per_page'] ?? null));
    }

    public function ver(Producto $producto): Producto
    {
        return $producto->load([
            'inventarios.almacen',
            'enfermedadesRecomendadas:id,nombre_comun,codigo_enfermedad,nivel_riesgo_base',
            'usuarioRegistro:id,name,paterno',
            'usuarioActualizacion:id,name,paterno',
        ]);
    }

    public function crear(ProductoDTO $dto, int $idUsuario): Producto
    {
        return DB::transaction(function () use ($dto, $idUsuario) {
            $data = $dto->toArray();
            // La columna no admite nulos: sin stock mínimo declarado se guarda 0.
            $data['stock_minimo'] ??= 0;
            $data['id_usuario_registro'] = $idUsuario;
            $data['id_usuario_actualizacion'] = $idUsuario;

            return Producto::create($data)
                ->load($this->relations);
        });
    }

    public function actualizar(Producto $producto, ProductoDTO $dto, int $idUsuario): Producto
    {
        return DB::transaction(function () use ($producto, $dto, $idUsuario) {
            $data = $dto->toArray();
            $data['id_usuario_actualizacion'] = $idUsuario;

            $producto->update($data);

            // Las alertas de stock usan el mínimo guardado en cada inventario;
            // no hay pantalla para editarlo por almacén, así que se sincroniza aquí.
            if ($producto->wasChanged('stock_minimo')) {
                $producto->inventarios()->update(['stock_minimo' => $producto->stock_minimo ?? 0]);
            }

            return $producto->fresh()
                ->load($this->relations);
        });
    }

    public function cambiarEstado(Producto $producto): Producto
    {
        $producto->update([
            'activo' => ! $producto->activo,
        ]);

        return $producto->fresh()
            ->load($this->relations);
    }

    public function desactivar(Producto $producto): Producto
    {
        $producto->update([
            'activo' => false,
        ]);

        return $producto->fresh()
            ->load($this->relations);
    }
}
