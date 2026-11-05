<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtapaFenologica extends Model
{
    protected $table = 'etapas_fenologicas';

    protected $fillable = [
        'nombre',
        'codigo',
        'dias_desde_siembra_min',
        'dias_desde_siembra_max',
        'descripcion',
        'orden',
    ];

    public function cultivoVariedades()
    {
        return $this->hasMany(CultivoVariedad::class, 'id_etapa_fenologica');
    }

    public function inspecciones()
    {
        return $this->hasMany(Inspeccion::class, 'id_etapa_fenologica');
    }
}
