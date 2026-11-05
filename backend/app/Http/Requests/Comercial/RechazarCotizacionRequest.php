<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;

class RechazarCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_rechazo.required' => 'Debe ingresar el motivo del rechazo.',
            'motivo_rechazo.max' => 'El motivo del rechazo no debe superar los 500 caracteres.',
        ];
    }
}
