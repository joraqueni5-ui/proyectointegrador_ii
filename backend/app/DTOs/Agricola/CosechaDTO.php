<?php

namespace App\DTOs\Agricola;

class CosechaDTO
{
    public function __construct(
        public readonly int $id_parcela,
        public readonly ?int $id_cultivo,
        public readonly string $fecha_cosecha,
        public readonly float $cantidad_toneladas,
        public readonly string $calidad,
        public readonly string $destino,
        public readonly ?float $precio_unitario_bs = null,
        public readonly ?float $ingreso_estimado_bs = null,
        public readonly ?string $observaciones = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id_parcela: (int) $data['id_parcela'],
            id_cultivo: isset($data['id_cultivo']) && $data['id_cultivo'] !== null && $data['id_cultivo'] !== ''
                ? (int) $data['id_cultivo']
                : null,
            fecha_cosecha: $data['fecha_cosecha'],
            cantidad_toneladas: (float) $data['cantidad_toneladas'],
            calidad: $data['calidad'],
            destino: $data['destino'],
            precio_unitario_bs: isset($data['precio_unitario_bs']) && $data['precio_unitario_bs'] !== null && $data['precio_unitario_bs'] !== ''
                ? (float) $data['precio_unitario_bs']
                : null,
            ingreso_estimado_bs: isset($data['ingreso_estimado_bs']) && $data['ingreso_estimado_bs'] !== null && $data['ingreso_estimado_bs'] !== ''
                ? (float) $data['ingreso_estimado_bs']
                : null,
            observaciones: $data['observaciones'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id_parcela' => $this->id_parcela,
            'id_cultivo' => $this->id_cultivo,
            'fecha_cosecha' => $this->fecha_cosecha,
            'cantidad_toneladas' => $this->cantidad_toneladas,
            'calidad' => $this->calidad,
            'destino' => $this->destino,
            'precio_unitario_bs' => $this->precio_unitario_bs,
            'ingreso_estimado_bs' => $this->ingreso_estimado_bs,
            'observaciones' => $this->observaciones,
        ];
    }
}
