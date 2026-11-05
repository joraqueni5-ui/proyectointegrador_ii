<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\ComunidadDTO;
use App\Models\Comunidad;
use Illuminate\Database\Eloquent\Collection;

class ComunidadService
{
    // Listar todas las comunidades
    public function listar(): Collection
    {
        return Comunidad::where('activo', true)
            ->orderBy('nombre_comunidad')
            ->get();
    }

    // Listar todas incluidas inactivas (solo admin)
    public function listarTodas(): Collection
    {
        return Comunidad::withCount('productores')
            ->orderBy('nombre_comunidad')
            ->get();
    }

    // Crear nueva comunidad
    public function crear(ComunidadDTO $dto): Comunidad
    {
        return Comunidad::create($dto->toArray());
    }

    // Actualizar comunidad
    public function actualizar(Comunidad $comunidad, ComunidadDTO $dto): Comunidad
    {
        $comunidad->update($dto->toArray());
        $comunidad->refresh();

        return $comunidad;
    }

    // Eliminar (soft delete lógico)
    public function desactivar(Comunidad $comunidad): Comunidad
    {
        $comunidad->update([
            'activo' => false,
        ]);

        return $comunidad->fresh();
    }

    public function activar(Comunidad $comunidad): Comunidad
    {
        $comunidad->update([
            'activo' => true,
        ]);

        return $comunidad->fresh();
    }
}
