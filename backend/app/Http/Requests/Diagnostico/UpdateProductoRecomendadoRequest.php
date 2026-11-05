<?php

namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoRecomendadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_recomendacion' => [
                'sometimes',
                'in:Preventivo,Curativo,Control,Apoyo,Otro',
            ],

            'dosis_recomendada' => [
                'nullable',
                'string',
                'max:150',
            ],

            'prioridad' => [
                'sometimes',
                'in:Alta,Media,Baja',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],

            'activo' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_recomendacion.in' => 'El tipo de recomendación no es válido.',
            'dosis_recomendada.max' => 'La dosis recomendada no debe superar los 150 caracteres.',
            'prioridad.in' => 'La prioridad seleccionada no es válida.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
