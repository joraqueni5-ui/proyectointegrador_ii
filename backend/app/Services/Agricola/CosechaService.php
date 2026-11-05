<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\CosechaDTO;
use App\Exceptions\ReglaNegocioException;
use App\Models\Cosecha;
use App\Models\CultivoVariedad;
use App\Models\Parcela;
use App\Support\Paginacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CosechaService
{
    private array $relations = [
        'parcela:id,codigo_parcela,nombre_referencial,superficie_ha,id_productor',
        'parcela.productor:id,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad',
        'cultivo:id,variedad_papa,id_parcela,fecha_siembra,estado',
        'usuario:id,name,paterno',
    ];

    public function listar(Request $request): Collection|LengthAwarePaginator
    {
        $query = Cosecha::with($this->relations)
            ->when($request->buscar, fn ($query, $buscar) => $this->filtrarBusqueda($query, $buscar))
            ->when($request->id_parcela, fn ($q, $id) => $q->where('id_parcela', $id))
            ->when($request->id_cultivo, fn ($q, $id) => $q->where('id_cultivo', $id))
            ->when($request->calidad, fn ($q, $calidad) => $q->where('calidad', $calidad))
            ->when($request->destino, fn ($q, $destino) => $q->where('destino', $destino))
            ->when($request->desde, fn ($q, $desde) => $q->whereDate('fecha_cosecha', '>=', $desde))
            ->when($request->hasta, fn ($q, $hasta) => $q->whereDate('fecha_cosecha', '<=', $hasta))
            ->orderBy('fecha_cosecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->boolean('todos')) {
            return $query->get();
        }

        return $query->paginate(Paginacion::porPagina($request->query('per_page')));
    }

    public function listarPapelera(Request $request): Collection|LengthAwarePaginator
    {
        $query = Cosecha::onlyTrashed()
            ->with($this->relations)
            ->when($request->buscar, fn ($query, $buscar) => $this->filtrarBusqueda($query, $buscar))
            ->orderBy('deleted_at', 'desc');

        if ($request->boolean('todos')) {
            return $query->get();
        }

        return $query->paginate(Paginacion::porPagina($request->query('per_page')));
    }

    // Agrupado para que el OR de la búsqueda no anule los demás filtros.
    private function filtrarBusqueda($query, string $buscar): void
    {
        $query->where(function ($q) use ($buscar) {
            $q->whereHas('parcela', function ($p) use ($buscar) {
                $p->where('codigo_parcela', 'ILIKE', "%{$buscar}%")
                    ->orWhere('nombre_referencial', 'ILIKE', "%{$buscar}%");
            })
                ->orWhereHas('parcela.productor', function ($p) use ($buscar) {
                    $p->where('nombre_completo', 'ILIKE', "%{$buscar}%")
                        ->orWhere('carnet_identidad', 'ILIKE', "%{$buscar}%");
                })
                ->orWhereHas('cultivo', fn ($c) => $c->where('variedad_papa', 'ILIKE', "%{$buscar}%"));
        });
    }

    public function verDetalle(Cosecha $cosecha): Cosecha
    {
        return $cosecha->load($this->relations);
    }

    public function crear(CosechaDTO $dto, int $idUsuario): Cosecha
    {
        return DB::transaction(function () use ($dto, $idUsuario) {
            $data = $this->prepararDatosCalculados($dto->toArray());
            $data['id_usuario'] = $idUsuario;

            $cosecha = Cosecha::create($data);

            $this->marcarCultivoComoCosechado($cosecha->id_cultivo);

            return $cosecha->fresh()
                ->load($this->relations);
        });
    }

    public function actualizar(Cosecha $cosecha, CosechaDTO $dto): Cosecha
    {
        return DB::transaction(function () use ($cosecha, $dto) {
            $cultivoAnteriorId = $cosecha->id_cultivo;

            $data = $this->prepararDatosCalculados($dto->toArray());

            $cosecha->update($data);

            $this->marcarCultivoComoCosechado($cosecha->id_cultivo);

            if ($cultivoAnteriorId && (int) $cultivoAnteriorId !== (int) $cosecha->id_cultivo) {
                $this->activarCultivoSiNoTieneCosechas($cultivoAnteriorId);
            }

            return $cosecha->fresh()
                ->load($this->relations);
        });
    }

    public function eliminar(Cosecha $cosecha): void
    {
        DB::transaction(function () use ($cosecha) {
            $cultivoId = $cosecha->id_cultivo;

            $cosecha->delete();

            $this->activarCultivoSiNoTieneCosechas($cultivoId);
        });
    }

    public function restaurar(int $id): Cosecha
    {
        return DB::transaction(function () use ($id) {
            $cosecha = Cosecha::onlyTrashed()->findOrFail($id);

            if ($cosecha->id_cultivo && Cosecha::where('id_cultivo', $cosecha->id_cultivo)->exists()) {
                throw new ReglaNegocioException('No se puede restaurar: el cultivo ya tiene otra cosecha registrada.');
            }

            $cosecha->restore();

            $this->marcarCultivoComoCosechado($cosecha->id_cultivo);

            return $cosecha->fresh()
                ->load($this->relations);
        });
    }

    private function prepararDatosCalculados(array $data): array
    {
        $parcela = Parcela::find($data['id_parcela']);

        if ($parcela && (float) $parcela->superficie_ha > 0) {
            $data['rendimiento_t_ha'] = round(
                (float) $data['cantidad_toneladas'] / (float) $parcela->superficie_ha,
                3
            );
        } else {
            $data['rendimiento_t_ha'] = null;
        }

        if (
            isset($data['precio_unitario_bs']) &&
            $data['precio_unitario_bs'] !== null &&
            ($data['ingreso_estimado_bs'] === null || $data['ingreso_estimado_bs'] === '')
        ) {
            $quintales = (float) $data['cantidad_toneladas'] * 10;

            $data['ingreso_estimado_bs'] = round(
                $quintales * (float) $data['precio_unitario_bs'],
                2
            );
        }

        return $data;
    }

    private function marcarCultivoComoCosechado(?int $cultivoId): void
    {
        if (! $cultivoId) {
            return;
        }

        CultivoVariedad::where('id', $cultivoId)
            ->update([
                'estado' => 'Cosechado',
            ]);
    }

    private function activarCultivoSiNoTieneCosechas(?int $cultivoId): void
    {
        if (! $cultivoId) {
            return;
        }

        $tieneCosechasActivas = Cosecha::where('id_cultivo', $cultivoId)
            ->exists();

        if (! $tieneCosechasActivas) {
            CultivoVariedad::where('id', $cultivoId)
                ->where('estado', 'Cosechado')
                ->update([
                    'estado' => 'Activo',
                ]);
        }
    }
}
