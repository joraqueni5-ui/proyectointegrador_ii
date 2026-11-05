<?php

namespace App\DTOs\Comercial;

class FacturaDTO
{
    public function __construct(
        public readonly int $id_productor,
        public readonly int $id_usuario,
        public readonly string $tipo,
        public readonly string $metodo_pago,
        public readonly array $detalles,
        public readonly ?string $fecha_emision = null,
        public readonly ?string $fecha_vencimiento = null,
        public readonly float $descuento = 0,
        public readonly ?string $observaciones = null,
        public readonly string $estado = 'Pagada',
    ) {}

    public static function fromRequest(array $data): self
    {
        $metodoPago = $data['metodo_pago'] ?? 'Efectivo';

        return new self(
            id_productor: (int) $data['id_productor'],
            id_usuario: (int) $data['id_usuario'],
            tipo: $data['tipo'] ?? 'Venta',
            metodo_pago: $metodoPago,
            detalles: $data['detalles'] ?? [],
            fecha_emision: $data['fecha_emision'] ?? now()->toDateString(),
            fecha_vencimiento: $data['fecha_vencimiento'] ?? null,
            descuento: isset($data['descuento']) ? (float) $data['descuento'] : 0,
            observaciones: $data['observaciones'] ?? null,
            estado: $data['estado'] ?? ($metodoPago === 'Credito' ? 'Pendiente' : 'Pagada'),
        );
    }

    public function toArray(): array
    {
        return [
            'id_productor' => $this->id_productor,
            'id_usuario' => $this->id_usuario,
            'tipo' => $this->tipo,
            'metodo_pago' => $this->metodo_pago,
            'fecha_emision' => $this->fecha_emision,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'descuento' => $this->descuento,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
        ];
    }
}
