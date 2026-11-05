<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Aborta con 403 si el usuario no tiene el permiso indicado.
     * Si se pasa un arreglo, basta con que tenga uno de ellos.
     */
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
