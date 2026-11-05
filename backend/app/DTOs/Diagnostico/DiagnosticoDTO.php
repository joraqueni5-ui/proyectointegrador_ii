<?php

namespace App\DTOs\Diagnostico;

use Illuminate\Http\UploadedFile;

class DiagnosticoDTO
{
    public function __construct(
        public readonly int $id_inspeccion,
        public readonly UploadedFile $fotografia,
    ) {}

    public static function fromRequest(array $data, UploadedFile $fotografia): self
    {
        return new self(
            id_inspeccion: (int) $data['id_inspeccion'],
            fotografia: $fotografia,
        );
    }

    public function toArray(string $ruta, string $nombreArchivo): array
    {
        return [
            'id_inspeccion' => $this->id_inspeccion,
            'ruta_fotografia' => $ruta,
            'nombre_archivo' => $nombreArchivo,
        ];
    }
}
