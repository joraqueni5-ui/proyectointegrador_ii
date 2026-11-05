<?php

namespace App\DTOs\Comercial;

class AlmacenDTO
{
    public function __construct(
        public readonly string $nombre_almacen,
        public readonly string $departamento,
        public readonly ?string $ubicacion = null,
        public readonly ?int $id_responsable = null,
        public readonly float $capacidad_maxima_m3 = 0,
        public readonly ?string $telefono_contacto = null,
        public readonly ?string $observaciones = null,
        public readonly string $estado = 'Activo',
    ) {}

    public static function fromRequest(array $data, ?int $userId = null): self
    {
        return new self(
            nombre_almacen: $data['nombre_almacen'],
            departamento: $data['departamento'],
            ubicacion: $data['ubicacion'] ?? null,
            id_responsable: isset($data['id_responsable'])
                ? (int) $data['id_responsable']
                : $userId,
            capacidad_maxima_m3: isset($data['capacidad_maxima_m3'])
                ? (float) $data['capacidad_maxima_m3']
                : 0,
            telefono_contacto: $data['telefono_contacto'] ?? null,
            observaciones: $data['observaciones'] ?? null,
            estado: $data['estado'] ?? 'Activo',
        );
    }

    public function toArray(): array
    {
        return [
            'nombre_almacen' => $this->nombre_almacen,
            'ubicacion' => $this->ubicacion,
            'departamento' => $this->departamento,
            'id_responsable' => $this->id_responsable,
            'capacidad_maxima_m3' => $this->capacidad_maxima_m3,
            'telefono_contacto' => $this->telefono_contacto,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
        ];
    }
}
