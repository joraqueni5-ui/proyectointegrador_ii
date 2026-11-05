<?php

namespace App\DTOs\Agricola;

class CultivoVariedadDTO
{
    public function __construct(
        public readonly int $id_parcela,
        public readonly string $variedad_papa,
        public readonly string $fecha_siembra,
        public readonly ?float $cantidad_semilla_kg = null,
        public readonly ?string $procedencia_semilla = null,
        public readonly ?int $id_etapa_fenologica = null,
        public readonly ?string $fecha_estimada_cosecha = null,
        public readonly ?string $observaciones = null,
        public readonly string $estado = 'Activo',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id_parcela: (int) $data['id_parcela'],
            variedad_papa: $data['variedad_papa'],
            fecha_siembra: $data['fecha_siembra'],
            cantidad_semilla_kg: isset($data['cantidad_semilla_kg']) && $data['cantidad_semilla_kg'] !== null
                ? (float) $data['cantidad_semilla_kg']
                : null,
            procedencia_semilla: $data['procedencia_semilla'] ?? null,
            id_etapa_fenologica: isset($data['id_etapa_fenologica']) && $data['id_etapa_fenologica'] !== null
                ? (int) $data['id_etapa_fenologica']
                : null,
            fecha_estimada_cosecha: $data['fecha_estimada_cosecha'] ?? null,
            observaciones: $data['observaciones'] ?? null,
            estado: $data['estado'] ?? 'Activo',
        );
    }

    public function toArray(): array
    {
        return [
            'id_parcela' => $this->id_parcela,
            'variedad_papa' => $this->variedad_papa,
            'fecha_siembra' => $this->fecha_siembra,
            'cantidad_semilla_kg' => $this->cantidad_semilla_kg,
            'procedencia_semilla' => $this->procedencia_semilla,
            'id_etapa_fenologica' => $this->id_etapa_fenologica,
            'fecha_estimada_cosecha' => $this->fecha_estimada_cosecha,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
        ];
    }
}
