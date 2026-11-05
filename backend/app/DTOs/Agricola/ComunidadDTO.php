<?php

namespace App\DTOs\Agricola;

class ComunidadDTO
{
    public function __construct(
        public readonly string $nombre_comunidad,
        public readonly string $municipio,
        public readonly ?string $provincia = null,
        public readonly string $departamento = 'La Paz',
        public readonly ?float $latitud = null,
        public readonly ?float $longitud = null,
        public readonly ?int $altitud_msnm = null,
        public readonly bool $activo = true,
    ) {}

    // Crea el DTO desde un Request de Laravel
    public static function fromRequest(array $data): self
    {
        return new self(
            nombre_comunidad: $data['nombre_comunidad'],
            municipio: $data['municipio'],
            provincia: $data['provincia'] ?? null,
            departamento: $data['departamento'] ?? 'La Paz',
            latitud: $data['latitud'] ?? null,
            longitud: $data['longitud'] ?? null,
            altitud_msnm: $data['altitud_msnm'] ?? null,
            activo: $data['activo'] ?? true,
        );
    }

    // Convierte el DTO a array para guardar en BD
    public function toArray(): array
    {
        return [
            'nombre_comunidad' => $this->nombre_comunidad,
            'municipio' => $this->municipio,
            'provincia' => $this->provincia,
            'departamento' => $this->departamento,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'altitud_msnm' => $this->altitud_msnm,
            'activo' => $this->activo,
        ];
    }
}
