<?php

namespace App\Http\Requests\Agricola;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoParcelaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'in:Activa,Inactiva,En_descanso'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Debe seleccionar un estado.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
