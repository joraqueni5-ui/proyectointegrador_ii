<?php

namespace App\DTOs\Agricola;

class ProductorDTO
{
    public function __construct(
        public readonly int $id_comunidad,
        public readonly string $nombres,
        public readonly string $apellido_paterno,
        public readonly ?string $apellido_materno,
        public readonly string $carnet_identidad,
        public readonly string $ci_extension,
        public readonly ?string $complemento_ci = null,
        public readonly ?string $telefono = null,
        public readonly ?string $sexo = null,
        public readonly ?string $fecha_nacimiento = null,
        public readonly ?string $direccion = null,
        public readonly ?float $superficie_total_ha = null,
        public readonly ?string $observaciones = null,
        public readonly bool $activo = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id_comunidad: (int) $data['id_comunidad'],
            nombres: $data['nombres'],
            apellido_paterno: $data['apellido_paterno'],
            apellido_materno: $data['apellido_materno'] ?? null,
            carnet_identidad: $data['carnet_identidad'],
            ci_extension: $data['ci_extension'],
            complemento_ci: $data['complemento_ci'] ?? null,
            telefono: $data['telefono'] ?? null,
            sexo: $data['sexo'] ?? null,
            fecha_nacimiento: $data['fecha_nacimiento'] ?? null,
            direccion: $data['direccion'] ?? null,
            superficie_total_ha: isset($data['superficie_total_ha'])
                ? (float) $data['superficie_total_ha']
                : null,
            observaciones: $data['observaciones'] ?? null,
            activo: $data['activo'] ?? true,
        );
    }

    public function nombreCompleto(): string
    {
        return trim(implode(' ', array_filter([
            $this->nombres,
            $this->apellido_paterno,
            $this->apellido_materno,
        ])));
    }

    public function toArray(): array
    {
        return [
            'id_comunidad' => $this->id_comunidad,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'nombre_completo' => $this->nombreCompleto(),
            'carnet_identidad' => $this->carnet_identidad,
            'ci_extension' => $this->ci_extension,
            'complemento_ci' => $this->complemento_ci,
            'telefono' => $this->telefono,
            'sexo' => $this->sexo,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'direccion' => $this->direccion,
            'superficie_total_ha' => $this->superficie_total_ha,
            'observaciones' => $this->observaciones,
            'activo' => $this->activo,
        ];
    }
}
