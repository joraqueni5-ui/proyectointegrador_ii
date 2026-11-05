<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'id_diagnostico',
        'id_usuario_destino',
        'id_responsable',

        // Prioridad
        'nivel_riesgo',
        'titulo',
        'mensaje',
        'accion_recomendada',
        'puntaje_prioridad',
        'motivos_prioridad',
        'prioridad_automatica',
        'prioridad_calculada_at',

        // Fechas generales
        'fecha_emision',
        'fecha_limite_accion',
        'fecha_lectura',

        // Estado general de la alerta
        'leida',
        'estado',
        'accion_tomada',

        // Seguimiento fitosanitario
        'estado_seguimiento',
        'plan_manejo',
        'fecha_inicio_tratamiento',
        'proxima_revision',
        'ultima_revision_at',
        'progreso_seguimiento',
        'resultado_seguimiento',
        'observacion_cierre',
        'fecha_resolucion',
    ];

    protected $casts = [
        'leida' => 'boolean',

        'fecha_emision' => 'date',
        'fecha_limite_accion' => 'date',
        'fecha_lectura' => 'datetime',

        'puntaje_prioridad' => 'integer',
        'motivos_prioridad' => 'array',
        'prioridad_automatica' => 'boolean',
        'prioridad_calculada_at' => 'datetime',

        'fecha_inicio_tratamiento' => 'datetime',
        'proxima_revision' => 'datetime',
        'ultima_revision_at' => 'datetime',
        'fecha_resolucion' => 'datetime',

        'progreso_seguimiento' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function diagnostico()
    {
        return $this->belongsTo(
            DiagnosticoCNN::class,
            'id_diagnostico'
        );
    }

    public function usuarioDestino()
    {
        return $this->belongsTo(
            User::class,
            'id_usuario_destino'
        );
    }

    public function responsable()
    {
        return $this->belongsTo(
            User::class,
            'id_responsable'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS DE SEGUIMIENTO
    |--------------------------------------------------------------------------
    */

    public function estaResuelta(): bool
    {
        return $this->estado_seguimiento === 'Resuelta';
    }
}
