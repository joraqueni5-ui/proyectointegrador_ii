<?php

namespace App\Http\Controllers\Agricola;

use App\DTOs\Agricola\ProductorDTO;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agricola\StoreProductorRequest;
use App\Http\Requests\Agricola\UpdateProductorRequest;
use App\Models\Productor;
use App\Services\Agricola\ProductorService;
use Illuminate\Http\Request;

class ProductorController extends Controller
{
    public function __construct(
        private readonly ProductorService $service
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, ['ver_productores', 'gestionar_productores']);

        $productores = $this->service->listar($request);

        return response()->json($productores);
    }

    public function show(Request $request, Productor $productor)
    {
        $this->checkPermiso($request, ['ver_productores', 'gestionar_productores']);

        return response()->json(
            $this->service->verConParcelas($productor)
        );
    }

    public function store(StoreProductorRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        $dto = ProductorDTO::fromRequest($request->validated());

        $productor = $this->service->crear($dto);

        return response()->json(
            $productor->load('comunidad:id,nombre_comunidad,municipio,provincia,departamento'),
            201
        );
    }

    public function update(UpdateProductorRequest $request, Productor $productor)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        if ($productor->activo && $request->has('activo') && ! $request->boolean('activo')) {
            $this->validarSinParcelasActivas($productor);
        }

        $dto = ProductorDTO::fromRequest(
            array_merge($productor->toArray(), $request->validated())
        );

        $productor = $this->service->actualizar($productor, $dto);

        return response()->json(
            $productor->load('comunidad:id,nombre_comunidad,municipio,provincia,departamento')
        );
    }

    public function destroy(Request $request, Productor $productor)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        $this->validarSinParcelasActivas($productor);

        $productor = $this->service->desactivar($productor);

        return response()->json([
            'message' => 'Productor desactivado correctamente.',
            'productor' => $productor,
        ]);
    }

    public function activar(Request $request, Productor $productor)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        $productor = $this->service->activar($productor);

        return response()->json([
            'message' => 'Productor activado correctamente.',
            'productor' => $productor,
        ]);
    }

    private function validarSinParcelasActivas(Productor $productor): void
    {
        if ($productor->parcelas()->where('estado', '!=', 'Inactiva')->exists()) {
            throw new ReglaNegocioException('No se puede desactivar el productor porque tiene parcelas activas registradas.');
        }
    }
}
