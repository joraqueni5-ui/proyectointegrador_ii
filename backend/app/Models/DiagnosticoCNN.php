<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticoCNN extends Model
{
    protected $table = 'diagnosticos_cnn';

    protected $fillable = [
        'id_inspeccion',
        'id_enfermedad',
        'ruta_fotografia',
        'nombre_archivo',
        'porcentaje_confianza',
        'top3_predicciones',
        'detalle_inferencia',
        'modelo_version',
        'nivel_severidad',
        'recomendacion',
        'estado_validacion',
        'validado_por',
        'observaciones_validacion',
        'fecha_validacion',
    ];

    protected $casts = [
        'top3_predicciones' => 'array',
        'detalle_inferencia' => 'array',
        'fecha_validacion' => 'datetime',
    ];

    public function inspeccion()
    {
        return $this->belongsTo(Inspeccion::class, 'id_inspeccion');
    }

    public function enfermedad()
    {
        return $this->belongsTo(Enfermedad::class, 'id_enfermedad');
    }

    public function validadoPor()
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function alertas()
    {
        return $this->hasMany(Alerta::class, 'id_diagnostico');
    }
}
