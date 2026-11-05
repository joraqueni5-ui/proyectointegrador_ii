<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\ProductorDTO;
use App\Models\Productor;
use App\Support\Paginacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductorService
{
    public function listar(Request $request): LengthAwarePaginator|Collection
    {
        $query = Productor::with([
            'comunidad:id,nombre_comunidad,municipio,provincia,departamento',
        ])
            ->select([
                'id',
                'id_comunidad',
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'nombre_completo',
                'carnet_identidad',
                'ci_extension',
                'complemento_ci',
                'telefono',
                'sexo',
                'fecha_nacimiento',
                'direccion',
                'superficie_total_ha',
                'observaciones',
                'activo',
                'created_at',
                'updated_at',
            ])
            ->when($request->buscar, function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('nombre_completo', 'ILIKE', "%{$buscar}%")
                        ->orWhere('nombres', 'ILIKE', "%{$buscar}%")
                        ->orWhere('apellido_paterno', 'ILIKE', "%{$buscar}%")
                        ->orWhere('apellido_materno', 'ILIKE', "%{$buscar}%")
                        ->orWhere('carnet_identidad', 'ILIKE', "%{$buscar}%")
                        ->orWhere('ci_extension', 'ILIKE', "%{$buscar}%")
                        ->orWhere('complemento_ci', 'ILIKE', "%{$buscar}%")
                        ->orWhere('telefono', 'ILIKE', "%{$buscar}%");
                });
            })
            ->when($request->id_comunidad, function ($query, $idComunidad) {
                $query->where('id_comunidad', $idComunidad);
            })
            ->when($request->filled('activo'), function ($query) use ($request) {
                $activo = filter_var(
                    $request->activo,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );

                if ($activo !== null) {
                    $query->where('activo', $activo);
                }
            })
            ->when($request->sexo, function ($query, $sexo) {
                $query->where('sexo', $sexo);
            })
            ->orderBy('id', 'desc');

        if ($request->boolean('todos')) {
            return $query->get();
        }

        return $query->paginate(Paginacion::porPagina($request->query('per_page')));
    }

    public function verConParcelas(Productor $productor): Productor
    {
        return $productor->load([
            'comunidad:id,nombre_comunidad,municipio,provincia,departamento',
            'parcelas' => fn ($q) => $q->where('estado', '!=', 'Inactiva'),
        ]);
    }

    public function crear(ProductorDTO $dto): Productor
    {
        return Productor::create($dto->toArray())
            ->load('comunidad:id,nombre_comunidad,municipio,provincia,departamento');
    }

    public function actualizar(Productor $productor, ProductorDTO $dto): Productor
    {
        $productor->update($dto->toArray());

        return $productor->fresh()
            ->load('comunidad:id,nombre_comunidad,municipio,provincia,departamento');
    }

    public function desactivar(Productor $productor): Productor
    {
        $productor->update([
            'activo' => false,
        ]);

        return $productor->fresh()
            ->load('comunidad:id,nombre_comunidad,municipio,provincia,departamento');
    }

    public function activar(Productor $productor): Productor
    {
        $productor->update([
            'activo' => true,
        ]);

        return $productor->fresh()
            ->load('comunidad:id,nombre_comunidad,municipio,provincia,departamento');
    }
}
