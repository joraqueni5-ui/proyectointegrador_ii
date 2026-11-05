<?php

namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEnfermedadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enfermedad = $this->route('enfermedad');
        $enfermedadId = is_object($enfermedad) ? $enfermedad->id : $enfermedad;

        return [
            'nombre_comun' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('enfermedades', 'nombre_comun')->ignore($enfermedadId),
            ],

            'nombre_cientifico' => [
                'nullable',
                'string',
                'max:200',
            ],

            'codigo_enfermedad' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('enfermedades', 'codigo_enfermedad')->ignore($enfermedadId),
            ],

            'tipo' => [
                'sometimes',
                'in:Hongo,Bacteria,Virus,Nematodo,Otro',
            ],

            'sintomas' => [
                'nullable',
                'string',
            ],

            'descripcion' => [
                'nullable',
                'string',
            ],

            'medidas_control' => [
                'nullable',
                'string',
            ],

            'nivel_riesgo_base' => [
                'sometimes',
                'in:Bajo,Medio,Alto,Critico',
            ],

            'imagen_referencia' => [
                'nullable',
                'string',
                'max:255',
            ],

            'activo' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $actual = $this->route('enfermedad')?->codigo_enfermedad;
                $nuevo = $this->input('codigo_enfermedad');

                // La CNN identifica la enfermedad por este código (class_mapping.json).
                if ($actual && $nuevo && $nuevo !== $actual && str_starts_with($actual, 'CNN-')) {
                    $validator->errors()->add(
                        'codigo_enfermedad',
                        'Este código lo usa el modelo CNN y no se puede modificar.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_comun.unique' => 'Ya existe una enfermedad con ese nombre común.',
            'nombre_comun.max' => 'El nombre común no debe superar los 150 caracteres.',

            'codigo_enfermedad.unique' => 'Ya existe una enfermedad con ese código.',
            'codigo_enfermedad.max' => 'El código de enfermedad no debe superar los 20 caracteres.',

            'tipo.in' => 'El tipo de enfermedad no es válido.',
            'nivel_riesgo_base.in' => 'El nivel de riesgo no es válido.',

            'imagen_referencia.max' => 'La imagen de referencia no debe superar los 255 caracteres.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
