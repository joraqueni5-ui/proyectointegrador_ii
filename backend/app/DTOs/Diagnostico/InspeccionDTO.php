<?php

namespace App\DTOs\Diagnostico;

class InspeccionDTO
{
    public function __construct(
        public readonly int $id_usuario, // Técnico asignado
        public readonly int $id_programado_por, // Usuario que programa
        public readonly int $id_parcela,
        public readonly string $fecha_visita,

        public readonly ?int $id_validado_por = null,
        public readonly ?int $id_cultivo = null,
        public readonly ?int $id_etapa_fenologica = null,

        public readonly string $tipo_inspeccion = 'Cultivo',

        public readonly ?string $hora_visita = null,
        public readonly ?string $condicion_climatica = null,
        public readonly ?float $temperatura_c = null,
        public readonly ?int $humedad_relativa_pct = null,
        public readonly ?float $pct_plantas_afectadas = null,

        public readonly ?string $observaciones = null,
        public readonly string $estado = 'Pendiente',

        public readonly ?string $motivo_no_realizada = null,
        public readonly ?string $fecha_reprogramada = null,
        public readonly ?string $recomendacion_reprogramacion = null,

        public readonly ?string $condicion_terreno = null,
        public readonly ?string $recomendacion_pre_siembra = null,
    ) {}

    public static function fromRequest(array $data, int $userId): self
    {
        return new self(
            id_usuario: (int) ($data['id_usuario'] ?? $userId),
            id_programado_por: (int) ($data['id_programado_por'] ?? $userId),
            id_parcela: (int) $data['id_parcela'],
            fecha_visita: $data['fecha_visita'],

            id_validado_por: $data['id_validado_por'] ?? null,
            id_cultivo: $data['id_cultivo'] ?? null,
            id_etapa_fenologica: $data['id_etapa_fenologica'] ?? null,

            tipo_inspeccion: $data['tipo_inspeccion'] ?? 'Cultivo',

            hora_visita: $data['hora_visita'] ?? null,
            condicion_climatica: $data['condicion_climatica'] ?? null,
            temperatura_c: $data['temperatura_c'] ?? null,
            humedad_relativa_pct: $data['humedad_relativa_pct'] ?? null,
            pct_plantas_afectadas: $data['pct_plantas_afectadas'] ?? null,

            observaciones: $data['observaciones'] ?? null,
            estado: $data['estado'] ?? 'Pendiente',

            motivo_no_realizada: $data['motivo_no_realizada'] ?? null,
            fecha_reprogramada: $data['fecha_reprogramada'] ?? null,
            recomendacion_reprogramacion: $data['recomendacion_reprogramacion'] ?? null,

            condicion_terreno: $data['condicion_terreno'] ?? null,
            recomendacion_pre_siembra: $data['recomendacion_pre_siembra'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id_usuario' => $this->id_usuario,
            'id_programado_por' => $this->id_programado_por,
            'id_validado_por' => $this->id_validado_por,

            'id_parcela' => $this->id_parcela,
            'id_cultivo' => $this->id_cultivo,
            'id_etapa_fenologica' => $this->id_etapa_fenologica,

            'tipo_inspeccion' => $this->tipo_inspeccion,

            'fecha_visita' => $this->fecha_visita,
            'hora_visita' => $this->hora_visita,

            'condicion_climatica' => $this->condicion_climatica,
            'temperatura_c' => $this->temperatura_c,
            'humedad_relativa_pct' => $this->humedad_relativa_pct,
            'pct_plantas_afectadas' => $this->pct_plantas_afectadas,

            'observaciones' => $this->observaciones,
            'estado' => $this->estado,

            'motivo_no_realizada' => $this->motivo_no_realizada,
            'fecha_reprogramada' => $this->fecha_reprogramada,
            'recomendacion_reprogramacion' => $this->recomendacion_reprogramacion,

            'condicion_terreno' => $this->condicion_terreno,
            'recomendacion_pre_siembra' => $this->recomendacion_pre_siembra,
        ];
    }
}
