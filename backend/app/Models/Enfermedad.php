<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enfermedad extends Model
{
    protected $table = 'enfermedades';

    protected $fillable = [
        'nombre_comun',
        'nombre_cientifico',
        'codigo_enfermedad',
        'tipo',
        'sintomas',
        'descripcion',
        'medidas_control',
        'nivel_riesgo_base',
        'imagen_referencia',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function diagnosticos()
    {
        return $this->hasMany(DiagnosticoCNN::class, 'id_enfermedad');
    }

    public function productosRecomendados()
    {
        return $this->belongsToMany(
            Producto::class,
            'enfermedad_producto_recomendados',
            'id_enfermedad',
            'id_producto'
        )
            ->withPivot([
                'tipo_recomendacion',
                'dosis_recomendada',
                'prioridad',
                'observaciones',
                'activo',
            ])
            ->withTimestamps()
            ->wherePivot('activo', true);
    }
}
