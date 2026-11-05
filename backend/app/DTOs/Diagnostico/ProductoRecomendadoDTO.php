<?php

namespace App\DTOs\Diagnostico;

class ProductoRecomendadoDTO
{
    public function __construct(
        public readonly int $id_producto,
        public readonly string $tipo_recomendacion,
        public readonly string $prioridad,
        public readonly ?string $dosis_recomendada = null,
        public readonly ?string $observaciones = null,
        public readonly bool $activo = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id_producto: (int) $data['id_producto'],
            tipo_recomendacion: $data['tipo_recomendacion'],
            prioridad: $data['prioridad'],
            dosis_recomendada: $data['dosis_recomendada'] ?? null,
            observaciones: $data['observaciones'] ?? null,
            activo: $data['activo'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'id_producto' => $this->id_producto,
            'tipo_recomendacion' => $this->tipo_recomendacion,
            'prioridad' => $this->prioridad,
            'dosis_recomendada' => $this->dosis_recomendada,
            'observaciones' => $this->observaciones,
            'activo' => $this->activo,
        ];
    }

    public function toPivotArray(): array
    {
        return [
            'tipo_recomendacion' => $this->tipo_recomendacion,
            'prioridad' => $this->prioridad,
            'dosis_recomendada' => $this->dosis_recomendada,
            'observaciones' => $this->observaciones,
            'activo' => $this->activo,
            'updated_at' => now(),
        ];
    }
}
