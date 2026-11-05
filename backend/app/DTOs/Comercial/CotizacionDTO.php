<?php

namespace App\DTOs\Comercial;

class CotizacionDTO
{
    public function __construct(
        public readonly int $id_productor,
        public readonly int $id_usuario,
        public readonly string $metodo_pago,
        public readonly array $detalles,
        public readonly ?string $fecha_emision = null,
        public readonly ?string $fecha_vencimiento = null,
        public readonly float $descuento = 0,
        public readonly ?string $observaciones = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id_productor: (int) $data['id_productor'],
            id_usuario: (int) $data['id_usuario'],
            metodo_pago: $data['metodo_pago'] ?? 'Efectivo',
            detalles: $data['detalles'] ?? [],
            fecha_emision: $data['fecha_emision'] ?? now()->toDateString(),
            fecha_vencimiento: $data['fecha_vencimiento'] ?? null,
            descuento: isset($data['descuento']) ? (float) $data['descuento'] : 0,
            observaciones: $data['observaciones'] ?? null,
        );
    }
}
