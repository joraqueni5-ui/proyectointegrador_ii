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

    /** "Nombre Apellido" para listas y etiquetas (el nombre completo con CI va en el detalle). */
    public function getNombreCortoAttribute(): string
    {
        $nombre = trim((string) explode(' ', trim((string) $this->nombres))[0]);
        $corto = trim($nombre.' '.trim((string) $this->apellido_paterno));

        return $corto !== '' ? $corto : trim((string) $this->nombre_completo);
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
