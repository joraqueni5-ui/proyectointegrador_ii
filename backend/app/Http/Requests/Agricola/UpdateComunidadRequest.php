<?php

namespace App\Http\Requests\Agricola;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComunidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $comunidad = $this->route('comunidad');
        $comunidadId = is_object($comunidad) ? $comunidad->id : $comunidad;

        return [
            'nombre_comunidad' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('comunidades', 'nombre_comunidad')
                    ->where('municipio', $this->municipio)
                    ->ignore($comunidadId),
            ],
            'municipio' => ['sometimes', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'latitud' => ['nullable', 'numeric', 'between:-23,-9'],
            'longitud' => ['nullable', 'numeric', 'between:-70,-57'],
            'altitud_msnm' => ['nullable', 'integer', 'between:0,6600'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_comunidad.max' => 'El nombre de la comunidad no debe superar los 150 caracteres.',
            'nombre_comunidad.unique' => 'Ya existe una comunidad con ese nombre en el mismo municipio.',

            'municipio.max' => 'El municipio no debe superar los 100 caracteres.',
            'provincia.max' => 'La provincia no debe superar los 100 caracteres.',
            'departamento.max' => 'El departamento no debe superar los 100 caracteres.',

            'latitud.numeric' => 'La latitud debe ser un número.',
            'latitud.between' => 'La latitud debe estar dentro de Bolivia (entre -23 y -9).',

            'longitud.numeric' => 'La longitud debe ser un número.',
            'longitud.between' => 'La longitud debe estar dentro de Bolivia (entre -70 y -57).',

            'altitud_msnm.integer' => 'La altitud debe ser un número entero.',
            'altitud_msnm.between' => 'La altitud debe estar entre 0 y 6600 msnm.',

            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
