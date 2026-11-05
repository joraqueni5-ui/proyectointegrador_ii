<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'codigo_producto',
        'nombre_producto',
        'categoria',
        'descripcion',
        'unidad_medida',
        'precio_unitario',
        'precio_costo',
        'stock_minimo',
        'imagen_url',
        'activo',
        'id_usuario_registro',
        'id_usuario_actualizacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'precio_unitario' => 'decimal:2',
        'precio_costo' => 'decimal:2',
        'stock_minimo' => 'decimal:3',
    ];

    protected $appends = [
        'stock_total',
    ];

    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'id_producto');
    }

    public function detallesFactura()
    {
        return $this->hasMany(DetalleFactura::class, 'id_producto');
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'id_producto');
    }

    public function enfermedadesRecomendadas()
    {
        return $this->belongsToMany(
            Enfermedad::class,
            'enfermedad_producto_recomendados',
            'id_producto',
            'id_enfermedad'
        )
            ->withPivot([
                'tipo_recomendacion',
                'dosis_recomendada',
                'prioridad',
                'observaciones',
                'activo',
            ])
            ->withTimestamps();
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'id_usuario_registro');
    }

    public function usuarioActualizacion()
    {
        return $this->belongsTo(User::class, 'id_usuario_actualizacion');
    }

    public function getStockTotalAttribute(): float
    {
        try {
            if ($this->relationLoaded('inventarios')) {
                return (float) $this->inventarios->sum('stock_actual');
            }

            return (float) $this->inventarios()->sum('stock_actual');
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public function stockTotal(): float
    {
        return $this->stock_total;
    }
}
