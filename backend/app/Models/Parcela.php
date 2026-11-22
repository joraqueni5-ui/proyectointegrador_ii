<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Parcela extends Model
{
    protected $table = 'parcelas';

    protected $fillable = [
        'id_productor',
        'codigo_parcela',
        'nombre_referencial',
        'latitud',
        'longitud',
        'coordenadas_texto',
        'altitud_msnm',
        'superficie_ha',
        'tipo_suelo',
        'pendiente',
        'sistema_riego',
        'observaciones',
        'estado',
    ];

    /**
     * Nombres legibles. Se calculan SIN consultas: usan solo lo que ya está cargado en memoria
     * (ver scopeConEtiqueta para precargarlo en los listados).
     */
    protected $appends = ['nombre_visible', 'etiqueta', 'sin_nombre', 'estado_sanitario', 'dias_sin_visita'];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'altitud_msnm' => 'integer',
        'superficie_ha' => 'float',
        'alertas_activas' => 'integer',
        'alerta_puntaje' => 'integer',
    ];

    public function productor()
    {
        return $this->belongsTo(Productor::class, 'id_productor');
    }

    public function cultivoVariedades()
    {
        return $this->hasMany(CultivoVariedad::class, 'id_parcela');
    }

    public function inspecciones()
    {
        return $this->hasMany(Inspeccion::class, 'id_parcela');
    }

    public function cosechas()
    {
        return $this->hasMany(Cosecha::class, 'id_parcela');
    }

    // Cultivo activo actual
    public function cultivoActivo()
    {
        return $this->hasOne(CultivoVariedad::class, 'id_parcela')
            ->where('estado', 'Activo')
            ->latest();
    }

    /** Precarga lo necesario para la etiqueta legible con un número fijo de consultas. */
    public function scopeConEtiqueta(Builder $query): Builder
    {
        return self::conTodasLasColumnas($query)
            // Orden de la parcela entre las de su productor (por id): subconsulta correlacionada,
            // correcta aunque el listado esté filtrado o paginado.
            ->addSelect(DB::raw(
                '(select count(*) from parcelas as p2 where p2.id_productor = parcelas.id_productor and p2.id <= parcelas.id) as orden_en_productor'
            ))
            ->with([
                'productor:id,id_comunidad,nombre_completo,nombres,apellido_paterno,apellido_materno,carnet_identidad,telefono',
                'productor.comunidad:id,nombre_comunidad,municipio',
            ]);
    }

    /** Agrega `alertas_activas`: alertas en estado Activa de la parcela (subconsulta, sin N+1). */
    public function scopeConAlertasActivas(Builder $query): Builder
    {
        return self::conTodasLasColumnas($query)
            ->addSelect([
                'alertas_activas' => DB::table('alertas')
                    ->join('diagnosticos_cnn', 'diagnosticos_cnn.id', '=', 'alertas.id_diagnostico')
                    ->join('inspecciones', 'inspecciones.id', '=', 'diagnosticos_cnn.id_inspeccion')
                    ->whereColumn('inspecciones.id_parcela', 'parcelas.id')
                    ->where('alertas.estado', 'Activa')
                    ->selectRaw('count(*)'),
            ]);
    }

    private static function conTodasLasColumnas(Builder $query): Builder
    {
        if (! in_array('parcelas.*', $query->getQuery()->columns ?? [], true)) {
            $query->addSelect('parcelas.*');
        }

        return $query;
    }

    /**
     * Resumen sanitario de cada parcela en UNA consulta (sin N+1): último diagnóstico con su foto,
     * última visita realizada, alertas activas y la prioridad más alta. Sin migraciones.
     */
    public function scopeConResumenSanitario(Builder $query): Builder
    {
        $ultimoDiagnostico = DB::table('diagnosticos_cnn as d')
            ->join('inspecciones as i', 'i.id', '=', 'd.id_inspeccion')
            ->selectRaw(
                'distinct on (i.id_parcela) i.id_parcela as ud_parcela, d.ruta_fotografia as ultima_foto, '
                .'d.id_enfermedad as ud_enfermedad, d.estado_validacion as ultimo_diagnostico_estado, '
                .'d.porcentaje_confianza as ultimo_diagnostico_confianza, d.created_at as ultimo_diagnostico_fecha'
            )
            ->orderBy('i.id_parcela')
            ->orderByDesc('d.created_at')
            ->orderByDesc('d.id');

        $alertasActivas = fn () => DB::table('alertas')
            ->join('diagnosticos_cnn', 'diagnosticos_cnn.id', '=', 'alertas.id_diagnostico')
            ->join('inspecciones', 'inspecciones.id', '=', 'diagnosticos_cnn.id_inspeccion')
            ->whereColumn('inspecciones.id_parcela', 'parcelas.id')
            ->where('alertas.estado', 'Activa');

        return self::conTodasLasColumnas($query)
            ->leftJoinSub($ultimoDiagnostico, 'ud', 'ud.ud_parcela', '=', 'parcelas.id')
            ->leftJoin('enfermedades as ue', 'ue.id', '=', 'ud.ud_enfermedad')
            ->addSelect([
                'ud.ultima_foto',
                'ud.ultimo_diagnostico_estado',
                'ud.ultimo_diagnostico_confianza',
                'ud.ultimo_diagnostico_fecha',
                'ue.nombre_comun as ultimo_diagnostico_nombre',
                'ue.codigo_enfermedad as ultimo_diagnostico_codigo',
                'ultima_visita' => DB::table('inspecciones')
                    ->whereColumn('inspecciones.id_parcela', 'parcelas.id')
                    ->whereIn('inspecciones.estado', ['Completada', 'Validada'])
                    ->selectRaw('max(inspecciones.fecha_visita)'),
                'alertas_activas' => $alertasActivas()->selectRaw('count(*)'),
                'alerta_puntaje' => $alertasActivas()->selectRaw('max(alertas.puntaje_prioridad)'),
                'alerta_enfermedad' => $alertasActivas()
                    ->join('enfermedades', 'enfermedades.id', '=', 'diagnosticos_cnn.id_enfermedad')
                    ->orderByRaw('alertas.puntaje_prioridad desc nulls last')
                    ->orderByDesc('alertas.id')
                    ->limit(1)
                    ->select('enfermedades.nombre_comun'),
            ]);
    }

    /** Días desde la última visita realizada (null si nunca se visitó o no se cargó el resumen). */
    public function getDiasSinVisitaAttribute(): ?int
    {
        if (! array_key_exists('alertas_activas', $this->attributes) || empty($this->attributes['ultima_visita'])) {
            return null;
        }

        return (int) Carbon::parse($this->attributes['ultima_visita'])->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Color del mapa: descanso (gris: En_descanso o Inactiva) > alerta (naranja) > sin visita hace
     * más de 30 días o nunca visitada (ámbar) > sana (verde). Solo si se cargó el resumen.
     */
    public function getEstadoSanitarioAttribute(): ?string
    {
        if (! array_key_exists('alertas_activas', $this->attributes)) {
            return null;
        }

        if (in_array($this->attributes['estado'] ?? null, ['Inactiva', 'En_descanso'], true)) {
            return 'descanso';
        }

        if ((int) $this->attributes['alertas_activas'] > 0) {
            return 'alerta';
        }

        $dias = $this->dias_sin_visita;

        return $dias === null || $dias > 30 ? 'sin_visita' : 'sana';
    }

    public function getSinNombreAttribute(): bool
    {
        return trim((string) ($this->attributes['nombre_referencial'] ?? '')) === '';
    }

    /**
     * nombre_referencial; si está vacío, "Parcela N de <Nombre Apellido>" cuando N y el productor
     * vienen precargados, y si no, el código de la parcela.
     */
    public function getNombreVisibleAttribute(): ?string
    {
        if (! $this->sin_nombre) {
            return trim($this->attributes['nombre_referencial']);
        }

        $orden = $this->attributes['orden_en_productor'] ?? null;

        if ($orden !== null && $this->relationLoaded('productor') && $this->productor) {
            return 'Parcela '.(int) $orden.' de '.$this->productor->nombre_corto;
        }

        return $this->attributes['codigo_parcela'] ?? null;
    }

    /** "<Nombre> — <Productor corto> · <Comunidad>" con lo que esté cargado. */
    public function getEtiquetaAttribute(): ?string
    {
        $etiqueta = $this->nombre_visible;

        if ($this->relationLoaded('productor') && $this->productor) {
            $etiqueta .= ' — '.$this->productor->nombre_corto;

            if ($this->productor->relationLoaded('comunidad') && $this->productor->comunidad) {
                $etiqueta .= ' · '.$this->productor->comunidad->nombre_comunidad;
            }
        }

        return $etiqueta;
    }
}
