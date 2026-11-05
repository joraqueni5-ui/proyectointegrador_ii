<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cosecha extends Model
{
    use SoftDeletes;

    protected $table = 'cosechas';

    protected $fillable = [
        'id_parcela',
        'id_cultivo',
        'id_usuario',
        'fecha_cosecha',
        'cantidad_toneladas',
        'rendimiento_t_ha',
        'calidad',
        'destino',
        'precio_unitario_bs',
        'ingreso_estimado_bs',
        'observaciones',
    ];

    protected $casts = [
        'fecha_cosecha' => 'date',
        'cantidad_toneladas' => 'float',
        'rendimiento_t_ha' => 'float',
        'precio_unitario_bs' => 'float',
        'ingreso_estimado_bs' => 'float',
        'deleted_at' => 'datetime',
    ];

    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'id_parcela');
    }

    public function cultivo()
    {
        return $this->belongsTo(CultivoVariedad::class, 'id_cultivo');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
