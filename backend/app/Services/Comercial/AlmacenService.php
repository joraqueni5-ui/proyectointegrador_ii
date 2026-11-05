<?php

namespace App\Services\Comercial;

use App\DTOs\Comercial\AlmacenDTO;
use App\Models\Almacen;
use App\Support\Paginacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AlmacenService
{
    public function listar(array $filtros = []): LengthAwarePaginator|Collection
    {
        $search = $filtros['search'] ?? '';
        $estado = $filtros['estado'] ?? null;
        $departamento = $filtros['departamento'] ?? null;
        $perPage = Paginacion::porPagina($filtros['per_page'] ?? null);

        $query = Almacen::with([
            'responsable:id,name,paterno,materno',
        ])
            ->withCount('inventarios')
            ->withSum('inventarios as stock_total', 'stock_actual')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nombre_almacen', 'ILIKE', "%{$search}%")
                        ->orWhere('ubicacion', 'ILIKE', "%{$search}%")
                        ->orWhere('departamento', 'ILIKE', "%{$search}%");
                });
            })
            ->when($estado, function ($q) use ($estado) {
                $q->where('estado', $estado);
            })
            ->when($departamento, function ($q) use ($departamento) {
                $q->where('departamento', $departamento);
            })
            ->orderBy('id', 'desc');

        // Para selectores: todos los almacenes sin paginar.
        if (filter_var($filtros['todos'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    public function ver(Almacen $almacen): Almacen
    {
        return $almacen->load([
            'responsable:id,name,paterno,materno',
            'inventarios.producto',
        ]);
    }

    public function crear(AlmacenDTO $dto): Almacen
    {
        return DB::transaction(function () use ($dto) {
            return Almacen::create($dto->toArray())
                ->load('responsable:id,name,paterno,materno');
        });
    }

    public function actualizar(Almacen $almacen, AlmacenDTO $dto): Almacen
    {
        return DB::transaction(function () use ($almacen, $dto) {
            $almacen->update($dto->toArray());

            return $almacen->fresh()
                ->load('responsable:id,name,paterno,materno');
        });
    }

    public function cambiarEstado(Almacen $almacen): Almacen
    {
        $almacen->update([
            'estado' => $almacen->estado === 'Activo' ? 'Inactivo' : 'Activo',
        ]);

        return $almacen->fresh()
            ->load('responsable:id,name,paterno,materno');
    }

    public function desactivar(Almacen $almacen): Almacen
    {
        $almacen->update([
            'estado' => 'Inactivo',
        ]);

        return $almacen->fresh()
            ->load('responsable:id,name,paterno,materno');
    }
}
