<?php

namespace App\Services\Agricola;

use App\DTOs\Agricola\ProductorDTO;
use App\Models\Productor;
use App\Support\Paginacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            // Totales por productor con subconsultas (una sola consulta, sin N+1). Las parcelas
            // inactivas no cuentan, igual que en la validación de superficie al crear parcelas.
            ->addSelect([
                'parcelas_count' => $this->parcelasDelProductor()->selectRaw('count(*)'),
                'superficie_parcelas_ha' => $this->parcelasDelProductor()->selectRaw('coalesce(sum(parcelas.superficie_ha), 0)'),
                'parcelas_con_alerta' => $this->parcelasDelProductor()
                    ->join('inspecciones', 'inspecciones.id_parcela', '=', 'parcelas.id')
                    ->join('diagnosticos_cnn', 'diagnosticos_cnn.id_inspeccion', '=', 'inspecciones.id')
                    ->join('alertas', 'alertas.id_diagnostico', '=', 'diagnosticos_cnn.id')
                    ->where('alertas.estado', 'Activa')
                    ->selectRaw('count(distinct parcelas.id)'),
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

    private function parcelasDelProductor()
    {
        return DB::table('parcelas')
            ->whereColumn('parcelas.id_productor', 'productores.id')
            ->where('parcelas.estado', '!=', 'Inactiva');
    }

    /** $conCompras: solo para quien puede ver ventas (el historial de compras es información comercial). */
    public function verConParcelas(Productor $productor, bool $conCompras = false): Productor
    {
        $productor->load([
            'comunidad:id,nombre_comunidad,municipio,provincia,departamento',
            'parcelas' => fn ($q) => $q->where('estado', '!=', 'Inactiva')->conAlertasActivas()->orderBy('id'),
        ]);

        if ($conCompras) {
            $productor->load(['facturas' => fn ($q) => $q
                ->select('id', 'id_productor', 'nro_factura', 'tipo', 'fecha_emision', 'monto_total', 'estado')
                ->latest('fecha_emision')->latest('id')->limit(10)]);
        }

        return $productor;
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
