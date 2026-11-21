<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Aborta con 403 si el usuario no tiene el permiso indicado.
     * Si se pasa un arreglo, basta con que tenga uno de ellos.
     */
    /** Como checkPermiso, pero el usuario necesita TODOS los permisos de la lista. */
    protected function checkTodosLosPermisos(
        Request $request,
        array $permisos,
        string $mensaje = 'No tienes permiso para realizar esta acción.'
    ): void {
        $user = $request->user();

        if (! $user || ! $user->tieneTodosLosPermisos($permisos)) {
            abort(403, $mensaje);
        }
    }

    protected function checkPermiso(
        Request $request,
        string|array $permisos,
        string $mensaje = 'No tienes permiso para realizar esta acción.'
    ): void {
        $user = $request->user();

        if (! $user || ! $user->tieneAlgunoDeEstosPermisos((array) $permisos)) {
            abort(403, $mensaje);
        }
    }
}
