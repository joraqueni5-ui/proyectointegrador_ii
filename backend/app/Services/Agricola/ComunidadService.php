<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\ComunidadDTO;
use App\Models\Comunidad;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        // Totales por comunidad con subconsultas (una sola consulta, sin N+1). Las parcelas
        // inactivas no cuentan; "con alerta" = parcelas con alguna alerta en estado Activa.
        $parcelas = fn () => DB::table('parcelas')
            ->join('productores', 'productores.id', '=', 'parcelas.id_productor')
            ->whereColumn('productores.id_comunidad', 'comunidades.id')
            ->where('parcelas.estado', '!=', 'Inactiva');

        return Comunidad::query()
            ->select('comunidades.*')
            ->withCount('productores')
            ->addSelect([
                'parcelas_count' => $parcelas()->selectRaw('count(*)'),
                'superficie_total_ha' => $parcelas()->selectRaw('coalesce(sum(parcelas.superficie_ha), 0)'),
                'parcelas_con_alerta' => $parcelas()
                    ->join('inspecciones', 'inspecciones.id_parcela', '=', 'parcelas.id')
                    ->join('diagnosticos_cnn', 'diagnosticos_cnn.id_inspeccion', '=', 'inspecciones.id')
                    ->join('alertas', 'alertas.id_diagnostico', '=', 'diagnosticos_cnn.id')
                    ->where('alertas.estado', 'Activa')
                    ->selectRaw('count(distinct parcelas.id)'),
            ])
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
