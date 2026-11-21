<?php

namespace App\Services;

use App\DTOs\UserDTO;
use App\Models\User;
use App\Support\Paginacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function index(Request $request): LengthAwarePaginator|Collection
    {
        $query = User::with('rol:id,nombre,permisos,activo')
            ->when($request->buscar, function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('name', 'ILIKE', "%{$buscar}%")
                        ->orWhere('paterno', 'ILIKE', "%{$buscar}%")
                        ->orWhere('materno', 'ILIKE', "%{$buscar}%")
                        ->orWhere('ci', 'ILIKE', "%{$buscar}%")
                        ->orWhere('email', 'ILIKE', "%{$buscar}%")
                        ->orWhere('celular', 'ILIKE', "%{$buscar}%")
                        ->orWhere('cargo', 'ILIKE', "%{$buscar}%")
                        ->orWhere('area', 'ILIKE', "%{$buscar}%")
                        ->orWhere('oficina_regional', 'ILIKE', "%{$buscar}%");
                });
            })
            ->when($request->id_rol, function ($query, $idRol) {
                $query->where('id_rol', $idRol);
            })
            ->when($request->rol, function ($query, $rol) {
                $query->whereHas('rol', function ($q) use ($rol) {
                    $q->where('nombre', $rol);
                });
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
            ->orderBy('id', 'desc');

        if ($request->boolean('todos')) {
            return $query->get();
        }

        return $query->paginate(Paginacion::porPagina($request->get('per_page'), 10));
    }

    public function find(int $id): User
    {
        return User::with('rol:id,nombre,permisos,activo')
            ->findOrFail($id);
    }

    public function store(UserDTO $dto): User
    {
        $data = $dto->toArray();

        if ($dto->password) {
            $data['password'] = $dto->password;
        }

        $data['activo'] = $data['activo'] ?? true;

        return User::create($data)
            ->load('rol:id,nombre,permisos,activo');
    }

    public function update(int $id, UserDTO $dto): User
    {
        $user = User::findOrFail($id);

        $data = $dto->toArray();

        if ($dto->password) {
            $data['password'] = $dto->password;
        }

        $user->update($data);

        // Si el usuario queda desactivado, se cierran todas sus sesiones.
        if (array_key_exists('activo', $data) && $data['activo'] === false) {
            $user->tokens()->delete();
        }

        return $user->fresh()
            ->load('rol:id,nombre,permisos,activo');
    }

    public function desactivar(int $id): User
    {
        $user = User::findOrFail($id);

        $user->update([
            'activo' => false,
        ]);

        // Cierra todas las sesiones abiertas del usuario desactivado.
        $user->tokens()->delete();

        return $user->fresh()
            ->load('rol:id,nombre,permisos,activo');
    }

    public function activar(int $id): User
    {
        $user = User::findOrFail($id);

        $user->update([
            'activo' => true,
        ]);

        return $user->fresh()
            ->load('rol:id,nombre,permisos,activo');
    }
}
