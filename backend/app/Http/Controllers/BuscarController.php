<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarRequest;
use App\Models\Comunidad;
use App\Models\Parcela;
use App\Models\Producto;
use App\Models\Productor;

/**
 * Búsqueda general de la barra superior. Solo lectura: máximo 5 resultados por tipo y solo los
 * tipos que el rol puede abrir (el mismo permiso que exige la pantalla de destino).
 */
class BuscarController extends Controller
{
    private const LIMITE = 5;

    // GET /api/buscar?q=texto
    public function __invoke(BuscarRequest $request)
    {
        $q = $request->validated('q');
        $patron = '%'.addcslashes($q, '\%_').'%';
        $usuario = $request->user();
        $respuesta = [];

        if ($usuario->tieneAlgunoDeEstosPermisos(['gestionar_parcelas'])) {
            $respuesta['parcelas'] = Parcela::conEtiqueta()
                ->where(function ($query) use ($patron) {
                    $query->where('codigo_parcela', 'ILIKE', $patron)
                        ->orWhere('nombre_referencial', 'ILIKE', $patron)
                        ->orWhereHas('productor', fn ($p) => $p->where('nombre_completo', 'ILIKE', $patron)
                            ->orWhere('carnet_identidad', 'ILIKE', $patron)
                            ->orWhereHas('comunidad', fn ($c) => $c->where('nombre_comunidad', 'ILIKE', $patron)));
                })
                ->orderBy('codigo_parcela')
                ->limit(self::LIMITE)
                ->get()
                ->map(fn (Parcela $p) => [
                    'id' => $p->id,
                    'etiqueta' => $p->etiqueta,
                    'detalle' => $p->codigo_parcela,
                ])->values();
        }

        if ($usuario->tieneAlgunoDeEstosPermisos(['gestionar_productores'])) {
            $respuesta['productores'] = Productor::with('comunidad:id,nombre_comunidad')
                ->where(function ($query) use ($patron) {
                    $query->where('nombre_completo', 'ILIKE', $patron)
                        ->orWhere('carnet_identidad', 'ILIKE', $patron)
                        ->orWhere('telefono', 'ILIKE', $patron);
                })
                ->orderBy('nombre_completo')
                ->limit(self::LIMITE)
                ->get(['id', 'id_comunidad', 'nombres', 'apellido_paterno', 'nombre_completo', 'carnet_identidad', 'ci_extension'])
                ->map(fn (Productor $p) => [
                    'id' => $p->id,
                    'etiqueta' => $p->nombre_corto,
                    'detalle' => trim('CI '.$p->carnet_identidad.' '.$p->ci_extension.' · '.($p->comunidad->nombre_comunidad ?? 'sin comunidad'), ' ·'),
                ])->values();

            $respuesta['comunidades'] = Comunidad::where(function ($query) use ($patron) {
                $query->where('nombre_comunidad', 'ILIKE', $patron)->orWhere('municipio', 'ILIKE', $patron);
            })
                ->orderBy('nombre_comunidad')
                ->limit(self::LIMITE)
                ->get(['id', 'nombre_comunidad', 'municipio', 'provincia'])
                ->map(fn (Comunidad $c) => [
                    'id' => $c->id,
                    'etiqueta' => $c->nombre_comunidad,
                    'detalle' => collect([$c->municipio, $c->provincia])->filter()->implode(' · '),
                ])->values();
        }

        if ($usuario->tieneAlgunoDeEstosPermisos(['gestionar_productos'])) {
            $respuesta['productos'] = Producto::where(function ($query) use ($patron) {
                $query->where('nombre_producto', 'ILIKE', $patron)->orWhere('codigo_producto', 'ILIKE', $patron);
            })
                ->orderBy('nombre_producto')
                ->limit(self::LIMITE)
                ->get(['id', 'codigo_producto', 'nombre_producto', 'categoria'])
                ->map(fn (Producto $p) => [
                    'id' => $p->id,
                    'etiqueta' => $p->nombre_producto,
                    'detalle' => collect([$p->codigo_producto, $p->categoria])->filter()->implode(' · '),
                ])->values();
        }

        return response()->json($respuesta);
    }
}
