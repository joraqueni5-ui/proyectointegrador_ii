<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio incumplida (stock insuficiente, estado no permitido, etc.).
 * Se responde como 422 con el mensaje para el usuario; los demás errores
 * siguen siendo 500 y quedan registrados en el log.
 */
class ReglaNegocioException extends RuntimeException {}
