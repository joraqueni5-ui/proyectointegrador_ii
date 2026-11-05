<?php

namespace App\DTOs\Diagnostico;

class EnfermedadDTO
{
    public function __construct(
        public readonly string $nombre_comun,
        public readonly string $codigo_enfermedad,
        public readonly string $tipo,
        public readonly string $nivel_riesgo_base,
        public readonly ?string $nombre_cientifico = null,
        public readonly ?string $sintomas = null,
        public readonly ?string $descripcion = null,
        public readonly ?string $medidas_control = null,
        public readonly ?string $imagen_referencia = null,
        public readonly bool $activo = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nombre_comun: $data['nombre_comun'],
            codigo_enfermedad: $data['codigo_enfermedad'],
            tipo: $data['tipo'],
            nivel_riesgo_base: $data['nivel_riesgo_base'],
            nombre_cientifico: $data['nombre_cientifico'] ?? null,
            sintomas: $data['sintomas'] ?? null,
            descripcion: $data['descripcion'] ?? null,
            medidas_control: $data['medidas_control'] ?? null,
            imagen_referencia: $data['imagen_referencia'] ?? null,
            activo: $data['activo'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'nombre_comun' => $this->nombre_comun,
            'nombre_cientifico' => $this->nombre_cientifico,
            'codigo_enfermedad' => $this->codigo_enfermedad,
            'tipo' => $this->tipo,
            'sintomas' => $this->sintomas,
            'descripcion' => $this->descripcion,
            'medidas_control' => $this->medidas_control,
            'nivel_riesgo_base' => $this->nivel_riesgo_base,
            'imagen_referencia' => $this->imagen_referencia,
            'activo' => $this->activo,
        ];
    }
}
