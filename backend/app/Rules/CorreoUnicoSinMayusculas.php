<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El correo es único sin importar mayúsculas ("Ana@x.com" y "ana@x.com" son el mismo).
 */
class CorreoUnicoSinMayusculas implements ValidationRule
{
    public function __construct(private readonly ?int $ignorarId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existe = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $value))])
            ->when($this->ignorarId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($existe) {
            $fail('Este correo electrónico ya está registrado.');
        }
    }
}
