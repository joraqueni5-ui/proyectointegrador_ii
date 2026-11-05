<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = 'inventarios';

    protected $fillable = [
        'id_producto',
        'id_almacen',
        'stock_actual',
        'stock_minimo',
        'stock_maximo',
        'ultima_actualizacion',
    ];

    protected $casts = ['ultima_actualizacion' => 'datetime'];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'id_almacen');
    }
}
