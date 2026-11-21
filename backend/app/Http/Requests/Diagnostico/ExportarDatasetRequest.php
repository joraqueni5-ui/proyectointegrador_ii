<?php

namespace App\Http\Requests\Diagnostico;

/**
 * Exportación del dataset validado. Solo Administrador y Agrónomo (y Superadmin, que tiene
 * acceso total). El rol se verifica por nombre porque los permisos viven en datos (roles.permisos)
 * y no se modifican.
 *
 * Usa los mismos filtros (y validaciones) que la vista de desempeño del modelo.
 */
class ExportarDatasetRequest extends DesempenoModeloRequest
{
    public const ROLES_PERMITIDOS = ['Administrador', 'Agrónomo', 'Superadmin'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->tieneRol(self::ROLES_PERMITIDOS);
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Solo un administrador o un agrónomo puede exportar el dataset validado.');
    }
}
