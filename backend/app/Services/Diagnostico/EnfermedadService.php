<?php

namespace App\Services\Diagnostico;

use App\DTOs\Diagnostico\EnfermedadDTO;
use App\Models\Enfermedad;
use Illuminate\Support\Facades\DB;

class EnfermedadService
{
    public function listar(array $filtros = [])
    {
        return Enfermedad::query()
            ->withCount('diagnosticos')
            ->when(
                isset($filtros['activo']),
                fn ($q) => $q->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN))
            )
            ->when(
                $filtros['tipo'] ?? null,
                fn ($q, $tipo) => $q->where('tipo', $tipo)
            )
            ->when(
                $filtros['nivel_riesgo_base'] ?? null,
                fn ($q, $riesgo) => $q->where('nivel_riesgo_base', $riesgo)
            )
            ->when(
                $filtros['search'] ?? null,
                function ($q, $search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('nombre_comun', 'ILIKE', "%{$search}%")
                            ->orWhere('nombre_cientifico', 'ILIKE', "%{$search}%")
                            ->orWhere('codigo_enfermedad', 'ILIKE', "%{$search}%");
                    });
                }
            )
            ->orderBy('nombre_comun')
            ->get();
    }

    public function ver(Enfermedad $enfermedad): Enfermedad
    {
        return $enfermedad->load([
            'productosRecomendados.inventarios.almacen',
        ])->loadCount('diagnosticos');
    }

    public function crear(EnfermedadDTO $dto): Enfermedad
    {
        return DB::transaction(function () use ($dto) {
            return Enfermedad::create($dto->toArray());
        });
    }

    public function actualizar(Enfermedad $enfermedad, EnfermedadDTO $dto): Enfermedad
    {
        return DB::transaction(function () use ($enfermedad, $dto) {
            $enfermedad->update($dto->toArray());

            return $enfermedad->fresh();
        });
    }

    public function cambiarEstado(Enfermedad $enfermedad): Enfermedad
    {
        $enfermedad->update([
            'activo' => ! $enfermedad->activo,
        ]);

        return $enfermedad->fresh();
    }

    public function desactivar(Enfermedad $enfermedad): Enfermedad
    {
        $enfermedad->update([
            'activo' => false,
        ]);

        return $enfermedad->fresh();
    }
}
