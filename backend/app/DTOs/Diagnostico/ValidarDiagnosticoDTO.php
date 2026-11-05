<?php

namespace App\DTOs\Diagnostico;

class ValidarDiagnosticoDTO
{
    public function __construct(
        public readonly string $estado_validacion,
        public readonly ?int $id_enfermedad = null,
        public readonly ?string $nivel_severidad = null,
        public readonly ?float $pct_plantas_afectadas = null,
        public readonly ?string $observaciones_validacion = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            estado_validacion: $data['estado_validacion'],

            id_enfermedad: isset($data['id_enfermedad']) &&
                $data['id_enfermedad'] !== null &&
                $data['id_enfermedad'] !== ''
                ? (int) $data['id_enfermedad']
                : null,

            nivel_severidad: $data['nivel_severidad'] ?? null,

            pct_plantas_afectadas: isset($data['pct_plantas_afectadas']) &&
                $data['pct_plantas_afectadas'] !== null &&
                $data['pct_plantas_afectadas'] !== ''
                ? (float) $data['pct_plantas_afectadas']
                : null,

            observaciones_validacion: $data['observaciones_validacion'] ?? null,
        );
    }

    /**
     * Datos que pertenecen al diagnóstico CNN.
     */
    public function toArray(
        ?int $enfermedadActual = null,
        ?string $severidadActual = null
    ): array {
        return [
            'estado_validacion' => $this->estado_validacion,

            'id_enfermedad' => $this->id_enfermedad ?? $enfermedadActual,

            'nivel_severidad' => $this->nivel_severidad ?? $severidadActual,

            'observaciones_validacion' => $this->observaciones_validacion,
        ];
    }

    /**
     * Datos agronómicos que pertenecen a la inspección.
     */
    public function toInspeccionArray(): array
    {
        return [
            'pct_plantas_afectadas' => $this->pct_plantas_afectadas,
        ];
    }
}
