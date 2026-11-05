<?php

namespace App\DTOs\Comercial;

class ProductoDTO
{
    public function __construct(
        public readonly string $codigo_producto,
        public readonly string $nombre_producto,
        public readonly string $categoria,
        public readonly string $unidad_medida,
        public readonly float $precio_unitario,
        public readonly ?float $precio_costo = null,
        public readonly ?float $stock_minimo = null,
        public readonly ?string $descripcion = null,
        public readonly ?string $imagen_url = null,
        public readonly bool $activo = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            codigo_producto: $data['codigo_producto'],
            nombre_producto: $data['nombre_producto'],
            categoria: $data['categoria'],
            unidad_medida: $data['unidad_medida'],
            precio_unitario: $data['precio_unitario'],
            precio_costo: $data['precio_costo'] ?? null,
            stock_minimo: $data['stock_minimo'] ?? null,
            descripcion: $data['descripcion'] ?? null,
            imagen_url: $data['imagen_url'] ?? null,
            activo: $data['activo'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'codigo_producto' => $this->codigo_producto,
            'nombre_producto' => $this->nombre_producto,
            'categoria' => $this->categoria,
            'unidad_medida' => $this->unidad_medida,
            'precio_unitario' => $this->precio_unitario,
            'precio_costo' => $this->precio_costo,
            'stock_minimo' => $this->stock_minimo,
            'descripcion' => $this->descripcion,
            'imagen_url' => $this->imagen_url,
            'activo' => $this->activo,
        ];
    }
}
