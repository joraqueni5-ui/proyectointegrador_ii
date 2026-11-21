<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Parcela extends Model
{
    protected $table = 'parcelas';

    protected $fillable = [
        'id_productor',
        'codigo_parcela',
        'nombre_referencial',
        'latitud',
        'longitud',
        'coordenadas_texto',
        'altitud_msnm',
        'superficie_ha',
        'tipo_suelo',
        'pendiente',
        'sistema_riego',
        'observaciones',
        'estado',
    ];

    /**
     * Nombres legibles. Se calculan SIN consultas: usan solo lo que ya está cargado en memoria
     * (ver scopeConEtiqueta para precargarlo en los listados).
     */
    protected $appends = ['nombre_visible', 'etiqueta', 'sin_nombre'];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'altitud_msnm' => 'integer',
        'superficie_ha' => 'float',
    ];

    public function productor()
    {
        return $this->belongsTo(Productor::class, 'id_productor');
    }

    public function cultivoVariedades()
    {
        return $this->hasMany(CultivoVariedad::class, 'id_parcela');
    }

    public function inspecciones()
    {
        return $this->hasMany(Inspeccion::class, 'id_parcela');
    }

    public function cosechas()
    {
        return $this->hasMany(Cosecha::class, 'id_parcela');
    }

    // Cultivo activo actual
    public function cultivoActivo()
    {
        return $this->hasOne(CultivoVariedad::class, 'id_parcela')
            ->where('estado', 'Activo')
            ->latest();
    }

    /** Precarga lo necesario para la etiqueta legible con un número fijo de consultas. */
    public function scopeConEtiqueta(Builder $query): Builder
    {
        return $query
            ->addSelect('parcelas.*')
            // Orden de la parcela entre las de su productor (por id): subconsulta correlacionada,
            // correcta aunque el listado esté filtrado o paginado.
            ->addSelect(DB::raw(
                '(select count(*) from parcelas as p2 where p2.id_productor = parcelas.id_productor and p2.id <= parcelas.id) as orden_en_productor'
            ))
            ->with([
                'productor:id,id_comunidad,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad,telefono',
                'productor.comunidad:id,nombre_comunidad',
            ]);
    }

    public function getSinNombreAttribute(): bool
    {
        return trim((string) ($this->attributes['nombre_referencial'] ?? '')) === '';
    }

    /**
     * nombre_referencial; si está vacío, "Parcela N de <Nombre Apellido>" cuando N y el productor
     * vienen precargados, y si no, el código de la parcela.
     */
    public function getNombreVisibleAttribute(): ?string
    {
        if (! $this->sin_nombre) {
            return trim($this->attributes['nombre_referencial']);
        }

        $orden = $this->attributes['orden_en_productor'] ?? null;

        if ($orden !== null && $this->relationLoaded('productor') && $this->productor) {
            return 'Parcela '.(int) $orden.' de '.$this->productor->nombre_corto;
        }

        return $this->attributes['codigo_parcela'] ?? null;
    }

    /** "<Nombre> — <Productor corto> · <Comunidad>" con lo que esté cargado. */
    public function getEtiquetaAttribute(): ?string
    {
        $etiqueta = $this->nombre_visible;

        if ($this->relationLoaded('productor') && $this->productor) {
            $etiqueta .= ' — '.$this->productor->nombre_corto;

            if ($this->productor->relationLoaded('comunidad') && $this->productor->comunidad) {
                $etiqueta .= ' · '.$this->productor->comunidad->nombre_comunidad;
            }
        }

        return $etiqueta;
    }
}
