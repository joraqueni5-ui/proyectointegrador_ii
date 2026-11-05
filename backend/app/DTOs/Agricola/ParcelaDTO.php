<?php

namespace App\DTOs\Agricola;

class ParcelaDTO
{
    public function __construct(
        public readonly int $id_productor,
        public readonly string $codigo_parcela,
        public readonly ?string $nombre_referencial = null,
        public readonly ?float $latitud = null,
        public readonly ?float $longitud = null,
        public readonly ?string $coordenadas_texto = null,
        public readonly ?int $altitud_msnm = null,
        public readonly ?float $superficie_ha = null,
        public readonly ?string $tipo_suelo = null,
        public readonly ?string $pendiente = null,
        public readonly string $sistema_riego = 'Secano',
        public readonly ?string $observaciones = null,
        public readonly string $estado = 'Activa',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id_productor: $data['id_productor'],
            codigo_parcela: $data['codigo_parcela'],
            nombre_referencial: $data['nombre_referencial'] ?? null,
            latitud: $data['latitud'] ?? null,
            longitud: $data['longitud'] ?? null,
            coordenadas_texto: $data['coordenadas_texto'] ?? null,
            altitud_msnm: $data['altitud_msnm'] ?? null,
            superficie_ha: $data['superficie_ha'] ?? null,
            tipo_suelo: $data['tipo_suelo'] ?? null,
            pendiente: $data['pendiente'] ?? null,
            sistema_riego: $data['sistema_riego'] ?? 'Secano',
            observaciones: $data['observaciones'] ?? null,
            estado: $data['estado'] ?? 'Activa',
        );
    }

    public function toArray(): array
    {
        return [
            'id_productor' => $this->id_productor,
            'codigo_parcela' => $this->codigo_parcela,
            'nombre_referencial' => $this->nombre_referencial,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'coordenadas_texto' => $this->coordenadas_texto,
            'altitud_msnm' => $this->altitud_msnm,
            'superficie_ha' => $this->superficie_ha,
            'tipo_suelo' => $this->tipo_suelo,
            'pendiente' => $this->pendiente,
            'sistema_riego' => $this->sistema_riego,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
        ];
    }
}
