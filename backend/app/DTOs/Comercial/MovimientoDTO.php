<?php

namespace App\DTOs\Comercial;

class MovimientoDTO
{
    public function __construct(
        public readonly int $id_producto,
        public readonly int $id_almacen,
        public readonly string $tipo,
        public readonly float $cantidad,
        public readonly int $id_usuario,
        public readonly string $motivo,
        public readonly ?int $id_factura = null,
        public readonly ?string $observaciones = null,
        public readonly ?string $fecha_movimiento = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id_producto: (int) $data['id_producto'],
            id_almacen: (int) $data['id_almacen'],
            tipo: $data['tipo'],
            cantidad: (float) $data['cantidad'],
            id_usuario: (int) $data['id_usuario'],
            motivo: $data['motivo'],
            id_factura: isset($data['id_factura'])
                ? (int) $data['id_factura']
                : null,
            observaciones: $data['observaciones'] ?? null,
            fecha_movimiento: $data['fecha_movimiento'] ?? now()->toDateString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id_producto' => $this->id_producto,
            'id_almacen' => $this->id_almacen,
            'id_factura' => $this->id_factura,
            'id_usuario' => $this->id_usuario,
            'tipo' => $this->tipo,
            'cantidad' => $this->cantidad,
            'motivo' => $this->motivo,
            'observaciones' => $this->observaciones,
            'fecha_movimiento' => $this->fecha_movimiento,
        ];
    }
}
