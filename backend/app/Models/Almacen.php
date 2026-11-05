<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'almacenes';

    protected $fillable = [
        'nombre_almacen',
        'ubicacion',
        'departamento',
        'id_responsable',
        'capacidad_maxima_m3',
        'telefono_contacto',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'capacidad_maxima_m3' => 'decimal:2',
    ];

    public function responsable()
    {
        return $this->belongsTo(User::class, 'id_responsable');
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'id_almacen');
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'id_almacen');
    }
}
