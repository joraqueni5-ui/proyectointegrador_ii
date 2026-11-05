<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comunidad extends Model
{
    protected $table = 'comunidades';

    protected $fillable = [
        'nombre_comunidad',
        'municipio',
        'provincia',
        'departamento',
        'latitud',
        'longitud',
        'altitud_msnm',
        'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function productores()
    {
        return $this->hasMany(Productor::class, 'id_comunidad');
    }
}
