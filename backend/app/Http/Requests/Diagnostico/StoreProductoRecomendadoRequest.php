<?php

namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRecomendadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_producto' => [
                'required',
                'exists:productos,id',
            ],

            'tipo_recomendacion' => [
                'required',
                'in:Preventivo,Curativo,Control,Apoyo,Otro',
            ],

            'dosis_recomendada' => [
                'nullable',
                'string',
                'max:150',
            ],

            'prioridad' => [
                'required',
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
            'id_producto.required' => 'Debe seleccionar un producto.',
            'id_producto.exists' => 'El producto seleccionado no existe.',

            'tipo_recomendacion.required' => 'Debe seleccionar el tipo de recomendación.',
            'tipo_recomendacion.in' => 'El tipo de recomendación no es válido.',

            'dosis_recomendada.max' => 'La dosis recomendada no debe superar los 150 caracteres.',

            'prioridad.required' => 'Debe seleccionar la prioridad.',
            'prioridad.in' => 'La prioridad seleccionada no es válida.',

            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
