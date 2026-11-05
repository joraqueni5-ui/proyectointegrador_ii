<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Productor extends Model
{
    protected $table = 'productores';

    protected $fillable = [
        'id_comunidad',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'nombre_completo',
        'carnet_identidad',
        'ci_extension',
        'complemento_ci',
        'telefono',
        'sexo',
        'fecha_nacimiento',
        'direccion',
        'superficie_total_ha',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_nacimiento' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Productor $productor) {
            $partes = array_filter([
                $productor->nombres,
                $productor->apellido_paterno,
                $productor->apellido_materno,
            ]);

            if (count($partes) > 0) {
                $productor->nombre_completo = implode(' ', $partes);
            }
        });
    }

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class, 'id_comunidad');
    }

    public function parcelas()
    {
        return $this->hasMany(Parcela::class, 'id_productor');
    }

    public function facturas()
    {
        return $this->hasMany(Factura::class, 'id_productor');
    }
}
