<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CultivoVariedad extends Model
{
    protected $table = 'cultivo_variedades';

    protected $fillable = [
        'id_parcela',
        'variedad_papa',
        'fecha_siembra',
        'cantidad_semilla_kg',
        'procedencia_semilla',
        'id_etapa_fenologica',
        'fecha_estimada_cosecha',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha_siembra' => 'date',
        'fecha_estimada_cosecha' => 'date',
    ];

    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'id_parcela');
    }

    public function etapaFenologica()
    {
        return $this->belongsTo(EtapaFenologica::class, 'id_etapa_fenologica');
    }

    public function inspecciones()
    {
        return $this->hasMany(Inspeccion::class, 'id_cultivo');
    }

    public function cosechas()
    {
        return $this->hasMany(Cosecha::class, 'id_cultivo');
    }
}
