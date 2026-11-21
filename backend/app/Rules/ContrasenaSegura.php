<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Contraseña segura: 8 a 72 caracteres con mayúscula, minúscula, número y un símbolo.
 * (72 es el máximo que admite bcrypt.)
 */
class ContrasenaSegura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $texto = (string) $value;

        if (mb_strlen($texto) > 72) {
            $fail('La contraseña no puede superar los 72 caracteres.');

            return;
        }

        if (
            mb_strlen($texto) < 8
            || ! preg_match('/\p{Ll}/u', $texto)
            || ! preg_match('/\p{Lu}/u', $texto)
            || ! preg_match('/\d/', $texto)
            || ! preg_match('/[^\p{L}\d\s]/u', $texto)
        ) {
            $fail('La contraseña debe tener al menos 8 caracteres e incluir una mayúscula, una minúscula, un número y un símbolo (por ejemplo ! # $ %).');
        }
    }
}
