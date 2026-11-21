<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La CNN rechazó la fotografía (borrosa, oscura, no es una hoja de papa, baja certeza...).
 * Lleva un código estable y un consejo concreto para tomar otra foto; el frontend los muestra.
 */
class ImagenRechazadaException extends RuntimeException
{
    public function __construct(
        string $mensaje,
        private readonly int $estado = 422,
        private readonly string $codigo = 'imagen_no_valida',
        private readonly string $consejo = 'Tome otra fotografía de la hoja con buena luz y enfoque.'
    ) {
        parent::__construct($mensaje);
    }

    public function estado(): int
    {
        return $this->estado;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function consejo(): string
    {
        return $this->consejo;
    }
}
