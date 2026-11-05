<?php

namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnfermedadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_comun' => [
                'required',
                'string',
                'max:150',
                'unique:enfermedades,nombre_comun',
            ],

            'nombre_cientifico' => [
                'nullable',
                'string',
                'max:200',
            ],

            'codigo_enfermedad' => [
                'required',
                'string',
                'max:20',
                'unique:enfermedades,codigo_enfermedad',
            ],

            'tipo' => [
                'required',
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
                'required',
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

    public function messages(): array
    {
        return [
            'nombre_comun.required' => 'El nombre común de la enfermedad es obligatorio.',
            'nombre_comun.unique' => 'Ya existe una enfermedad con ese nombre común.',
            'nombre_comun.max' => 'El nombre común no debe superar los 150 caracteres.',

            'nombre_cientifico.max' => 'El nombre científico no debe superar los 200 caracteres.',

            'codigo_enfermedad.required' => 'El código de enfermedad es obligatorio.',
            'codigo_enfermedad.unique' => 'Ya existe una enfermedad con ese código.',
            'codigo_enfermedad.max' => 'El código de enfermedad no debe superar los 20 caracteres.',

            'tipo.required' => 'Debe seleccionar el tipo de enfermedad.',
            'tipo.in' => 'El tipo de enfermedad no es válido.',

            'nivel_riesgo_base.required' => 'Debe seleccionar el nivel de riesgo.',
            'nivel_riesgo_base.in' => 'El nivel de riesgo no es válido.',

            'imagen_referencia.max' => 'La imagen de referencia no debe superar los 255 caracteres.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
