<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\CultivoVariedadDTO;
use App\Models\CultivoVariedad;
use App\Support\Paginacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CultivoVariedadService
{
    public function listar(Request $request): LengthAwarePaginator|Collection
    {
        $query = CultivoVariedad::with([
            'parcela:id,codigo_parcela,nombre_referencial,id_productor,estado',
            'parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad',
            'etapaFenologica:id,nombre,codigo',
        ])
            ->when($request->buscar, function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('variedad_papa', 'ILIKE', "%{$buscar}%")
                        ->orWhere('procedencia_semilla', 'ILIKE', "%{$buscar}%")
                        ->orWhere('estado', 'ILIKE', "%{$buscar}%");
                });
            })
            ->when($request->id_parcela, function ($query, $idParcela) {
                $query->where('id_parcela', $idParcela);
            })
            ->when($request->estado, function ($query, $estado) {
                $query->where('estado', $estado);
            })
            ->orderBy('id', 'desc');

        if ($request->boolean('todos')) {
            return $query->get();
        }

        return $query->paginate(Paginacion::porPagina($request->query('per_page')));
    }

    public function verDetalle(CultivoVariedad $cultivo): CultivoVariedad
    {
        return $cultivo->load([
            'parcela:id,codigo_parcela,nombre_referencial,id_productor,estado,superficie_ha',
            'parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad',
            'etapaFenologica:id,nombre,codigo,descripcion',
            'inspecciones' => fn ($q) => $q->latest()->limit(5),
            'cosechas' => fn ($q) => $q->latest()->limit(5),
        ]);
    }

    public function crear(CultivoVariedadDTO $dto): CultivoVariedad
    {
        return CultivoVariedad::create($dto->toArray())
            ->load([
                'parcela:id,codigo_parcela,nombre_referencial,id_productor',
                'etapaFenologica:id,nombre,codigo',
            ]);
    }

    public function actualizar(CultivoVariedad $cultivo, CultivoVariedadDTO $dto): CultivoVariedad
    {
        $cultivo->update($dto->toArray());

        return $cultivo->fresh()
            ->load([
                'parcela:id,codigo_parcela,nombre_referencial,id_productor',
                'etapaFenologica:id,nombre,codigo',
            ]);
    }

    public function cambiarEstado(CultivoVariedad $cultivo, string $estado): CultivoVariedad
    {
        $cultivo->update([
            'estado' => $estado,
        ]);

        return $cultivo->fresh()
            ->load([
                'parcela:id,codigo_parcela,nombre_referencial,id_productor',
                'etapaFenologica:id,nombre,codigo',
            ]);
    }
}
