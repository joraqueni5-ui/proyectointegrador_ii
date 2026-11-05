<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspeccion extends Model
{
    protected $table = 'inspecciones';

    protected $fillable = [
        'id_usuario',
        'id_programado_por',
        'id_validado_por',
        'id_parcela',
        'id_cultivo',
        'id_etapa_fenologica',

        'tipo_inspeccion',

        'fecha_visita',
        'hora_visita',
        'fecha_inicio',
        'fecha_finalizacion',
        'fecha_validacion',

        'condicion_climatica',
        'temperatura_c',
        'humedad_relativa_pct',
        'pct_plantas_afectadas',

        'observaciones',
        'estado',

        'motivo_no_realizada',
        'fecha_reprogramada',
        'recomendacion_reprogramacion',

        'condicion_terreno',
        'recomendacion_pre_siembra',
    ];

    protected $casts = [
        'fecha_visita' => 'date',
        'fecha_reprogramada' => 'date',
        'fecha_inicio' => 'datetime',
        'fecha_finalizacion' => 'datetime',
        'fecha_validacion' => 'datetime',
    ];

    // Técnico asignado
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    // Usuario que programó la inspección
    public function programadoPor()
    {
        return $this->belongsTo(User::class, 'id_programado_por');
    }

    // Usuario que validó la inspección
    public function validadoPor()
    {
        return $this->belongsTo(User::class, 'id_validado_por');
    }

    public function parcela()
    {
        return $this->belongsTo(Parcela::class, 'id_parcela');
    }

    public function cultivo()
    {
        return $this->belongsTo(CultivoVariedad::class, 'id_cultivo');
    }

    public function etapaFenologica()
    {
        return $this->belongsTo(EtapaFenologica::class, 'id_etapa_fenologica');
    }

    public function diagnosticos()
    {
        return $this->hasMany(DiagnosticoCNN::class, 'id_inspeccion');
    }
}
