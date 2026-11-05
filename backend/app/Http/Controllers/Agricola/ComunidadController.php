<?php

namespace App\Http\Controllers\Agricola;

use App\DTOs\Agricola\ComunidadDTO;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agricola\StoreComunidadRequest;
use App\Http\Requests\Agricola\UpdateComunidadRequest;
use App\Models\Comunidad;
use App\Services\Agricola\ComunidadService;
use Illuminate\Http\Request;

class ComunidadController extends Controller
{
    public function __construct(
        private readonly ComunidadService $service
    ) {}

    // GET /api/comunidades
    public function index(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_productores');
        $comunidades = $request->query('todos')
            ? $this->service->listarTodas()
            : $this->service->listar();

        return response()->json($comunidades);
    }

    // GET /api/comunidades/{id}
    public function show(Request $request, Comunidad $comunidad)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        return response()->json(
            $comunidad->load('productores:id,id_comunidad,nombre_completo,nombres,apellido_paterno,apellido_materno')
        );
    }

    // POST /api/comunidades
    public function store(StoreComunidadRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        $data = $request->validated();

        $dto = ComunidadDTO::fromRequest($data);

        $comunidad = $this->service->crear($dto);

        return response()->json($comunidad, 201);
    }

    public function update(UpdateComunidadRequest $request, Comunidad $comunidad)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        $data = $request->validated();

        if ($comunidad->activo && isset($data['activo']) && ! filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN)) {
            $this->validarSinProductoresActivos($comunidad);
        }

        $dto = ComunidadDTO::fromRequest(
            array_merge($comunidad->toArray(), $data)
        );

        $comunidad = $this->service->actualizar($comunidad, $dto);

        return response()->json($comunidad);
    }

    // DELETE /api/comunidades/{id}
    public function destroy(Request $request, Comunidad $comunidad)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        $this->validarSinProductoresActivos($comunidad);

        $this->service->desactivar($comunidad);

        return response()->json([
            'message' => 'Comunidad desactivada correctamente.',
        ]);
    }

    public function activar(Request $request, Comunidad $comunidad)
    {
        $this->checkPermiso($request, 'gestionar_productores');

        $comunidad = $this->service->activar($comunidad);

        return response()->json([
            'message' => 'Comunidad activada correctamente.',
            'comunidad' => $comunidad,
        ]);
    }

    private function validarSinProductoresActivos(Comunidad $comunidad): void
    {
        if ($comunidad->productores()->where('activo', true)->exists()) {
            throw new ReglaNegocioException('No se puede desactivar la comunidad porque tiene productores activos registrados.');
        }
    }
}
