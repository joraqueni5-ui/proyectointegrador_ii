<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
