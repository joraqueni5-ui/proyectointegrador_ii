<?php

namespace App\Http\Requests\Agricola;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoCultivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                'in:Activo,Cosechado,Perdido,Abandonado',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Debe seleccionar un estado.',
            'estado.in' => 'El estado del cultivo no es válido. Solo se permite Activo, Cosechado, Perdido o Abandonado.',
        ];
    }
}
