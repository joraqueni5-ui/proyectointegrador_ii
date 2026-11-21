<?php

namespace App\Http\Controllers;

use App\DTOs\UserDTO;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Rol;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_usuarios');

        $users = $this->userService->index($request);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        $this->checkPermiso($request, 'gestionar_usuarios');

        $this->bloquearSiAdminAsignaSuperadmin(
            $request,
            $request->input('id_rol') ?? $request->input('rol_id')
        );

        $dto = UserDTO::fromArray($request->validated());

        $user = $this->userService->store($dto);

        return response()->json(
            $this->formatUser($user->load('rol:id,nombre,permisos,activo')),
            201
        );
    }

    public function show(Request $request, int $id)
    {
        $this->checkPermiso($request, 'gestionar_usuarios');

        $user = $this->userService->find($id);

        return response()->json(
            $this->formatUser($user)
        );
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $this->checkPermiso($request, 'gestionar_usuarios');

        $usuarioObjetivo = $this->userService->find($id);

        $this->bloquearSiAdminTocaSuperadmin($request, $usuarioObjetivo);

        $nuevoRol = $request->input('id_rol');

        // Nadie puede quitarse (ni cambiarse) su propio rol: así no se pierde el acceso de administración.
        if ($request->user()->id === $usuarioObjetivo->id && $nuevoRol && (int) $nuevoRol !== (int) $usuarioObjetivo->id_rol) {
            abort(403, 'No puedes cambiar tu propio rol. Pídeselo a otro administrador.');
        }

        if ($nuevoRol && (int) $nuevoRol !== (int) $usuarioObjetivo->id_rol && $this->esUltimoAdministradorActivo($usuarioObjetivo)) {
            abort(403, "No se puede cambiar el rol del último usuario activo con rol {$usuarioObjetivo->rol->nombre}.");
        }

        // Desactivar desde el formulario aplica las mismas reglas que el botón de baja.
        if ($usuarioObjetivo->activo && $request->has('activo') && ! $request->boolean('activo')) {
            $this->validarBajaLogica($request->user(), $usuarioObjetivo);
        }

        $this->bloquearSiAdminAsignaSuperadmin(
            $request,
            $request->input('id_rol') ?? $request->input('rol_id')
        );

        $dto = UserDTO::fromArray(
            array_merge($usuarioObjetivo->toArray(), $request->validated())
        );

        $user = $this->userService->update($id, $dto);

        return response()->json(
            $this->formatUser($user->load('rol:id,nombre,permisos,activo'))
        );
    }

    public function destroy(Request $request, int $id)
    {
        $this->checkPermiso($request, 'gestionar_usuarios');

        $usuarioAutenticado = $request->user();
        $usuarioObjetivo = $this->userService->find($id);

        $this->bloquearSiAdminTocaSuperadmin($request, $usuarioObjetivo);

        $this->validarBajaLogica($usuarioAutenticado, $usuarioObjetivo);

        $user = $this->userService->desactivar($id);

        return response()->json([
            'message' => 'Usuario desactivado correctamente.',
            'user' => $this->formatUser($user),
        ]);
    }

    public function activar(Request $request, int $id)
    {
        $this->checkPermiso($request, 'gestionar_usuarios');

        $usuarioObjetivo = $this->userService->find($id);

        $this->bloquearSiAdminTocaSuperadmin($request, $usuarioObjetivo);

        $user = $this->userService->activar($id);

        return response()->json([
            'message' => 'Usuario activado correctamente.',
            'user' => $this->formatUser($user),
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load('rol:id,nombre,permisos,activo');

        return response()->json(
            $this->formatUser($user)
        );
    }

    public function roles(Request $request)
    {
        $this->checkPermiso($request, 'gestionar_usuarios');

        $query = Rol::where('activo', true)
            ->orderBy('nombre');

        if (! $this->usuarioEsSuperadmin($request->user())) {
            $query->where('nombre', '!=', 'Superadmin');
        }

        $roles = $query->get([
            'id',
            'nombre',
            'descripcion',
            'permisos',
            'activo',
        ]);

        return response()->json($roles);
    }

    public function tecnicosInspeccion(Request $request)
    {
        $this->checkPermiso(
            $request,
            ['ver_inspecciones', 'gestionar_usuarios', 'ver_reportes', 'ver_alertas'],
            'No tienes permiso para listar técnicos.'
        );

        $tecnicos = User::with('rol:id,nombre')
            ->where('activo', true)
            ->whereHas('rol', function ($query) {
                $query->whereIn('nombre', [
                    'Técnico',
                    'Tecnico',
                    'Agrónomo',
                    'Agronomo',
                    'Administrador',
                ]);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'paterno' => $user->paterno,
                'materno' => $user->materno,
                'email' => $user->email,
                'cargo' => $user->cargo,
                'activo' => (bool) $user->activo,
                'rol' => [
                    'id' => $user->rol?->id,
                    'nombre' => $user->rol?->nombre,
                ],
            ]);

        return response()->json($tecnicos);
    }

    private function formatUser(User $user): array
    {
        $user->loadMissing('rol:id,nombre,permisos,activo');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'paterno' => $user->paterno,
            'materno' => $user->materno,

            'ci' => $user->ci,
            'ci_extension' => $user->ci_extension,
            'sexo' => $user->sexo,

            'email' => $user->email,
            'celular' => $user->celular,

            'cargo' => $user->cargo,
            'area' => $user->area,
            'oficina_regional' => $user->oficina_regional,

            'id_rol' => $user->id_rol,
            'rol' => [
                'id' => $user->rol?->id,
                'nombre' => $user->rol?->nombre,
            ],
            'permisos' => $user->permisos(),
            'activo' => (bool) $user->activo,
            'face_photo' => $user->face_photo,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function validarBajaLogica(User $usuarioAutenticado, User $usuarioObjetivo): void
    {
        $usuarioObjetivo->loadMissing('rol:id,nombre,permisos,activo');

        if ($usuarioAutenticado->id === $usuarioObjetivo->id) {
            abort(403, 'No puedes desactivar tu propia cuenta.');
        }

        if ($this->esUltimoAdministradorActivo($usuarioObjetivo)) {
            abort(403, "No se puede desactivar al último usuario activo con rol {$usuarioObjetivo->rol->nombre}.");
        }
    }

    private function esUltimoAdministradorActivo(User $usuario): bool
    {
        $usuario->loadMissing('rol:id,nombre,permisos,activo');
        $rol = $usuario->rol?->nombre;

        if (! in_array($rol, ['Superadmin', 'Administrador'], true)) {
            return false;
        }

        return User::whereHas('rol', fn ($query) => $query->where('nombre', $rol))
            ->where('activo', true)
            ->count() <= 1;
    }

    private function usuarioEsSuperadmin($user): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing('rol:id,nombre,permisos,activo');

        return $user->rol?->nombre === 'Superadmin';
    }

    private function rolEsSuperadmin($rolId): bool
    {
        if (! $rolId) {
            return false;
        }

        $rol = Rol::find($rolId);

        return $rol && $rol->nombre === 'Superadmin';
    }

    private function bloquearSiAdminTocaSuperadmin(Request $request, User $usuarioObjetivo): void
    {
        $usuarioActual = $request->user();

        if ($this->usuarioEsSuperadmin($usuarioActual)) {
            return;
        }

        if ($this->usuarioEsSuperadmin($usuarioObjetivo)) {
            abort(403, 'No puedes modificar, activar o inactivar a un usuario Superadmin.');
        }
    }

    private function bloquearSiAdminAsignaSuperadmin(Request $request, $rolId): void
    {
        $usuarioActual = $request->user();

        if ($this->usuarioEsSuperadmin($usuarioActual)) {
            return;
        }

        if ($this->rolEsSuperadmin($rolId)) {
            abort(403, 'No puedes crear ni asignar usuarios con rol Superadmin.');
        }
    }
}
