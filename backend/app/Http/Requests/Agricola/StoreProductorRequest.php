<?php

namespace App\Http\Requests\Agricola;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => $this->nombres ? trim((string) $this->nombres) : null,
            'apellido_paterno' => $this->apellido_paterno ? trim((string) $this->apellido_paterno) : null,
            'apellido_materno' => $this->apellido_materno ? trim((string) $this->apellido_materno) : null,
            'carnet_identidad' => $this->carnet_identidad
                ? preg_replace('/\D/', '', (string) $this->carnet_identidad)
                : null,
            'ci_extension' => $this->ci_extension
                ? strtoupper(trim((string) $this->ci_extension))
                : null,

            'complemento_ci' => $this->complemento_ci
                ? strtoupper(trim((string) $this->complemento_ci))
                : null,
            'telefono' => $this->telefono
                ? preg_replace('/\D/', '', (string) $this->telefono)
                : null,
            'direccion' => $this->direccion ? trim((string) $this->direccion) : null,
            'observaciones' => $this->observaciones ? trim((string) $this->observaciones) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id_comunidad' => [
                'required',
                'exists:comunidades,id',
            ],

            'nombres' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[\pL\s]+$/u',
            ],

            'apellido_paterno' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[\pL\s]+$/u',
            ],

            'apellido_materno' => [
                'nullable',
                'string',
                'min:3',
                'max:100',
                'regex:/^[\pL\s]+$/u',
            ],

            'carnet_identidad' => [
                'required',
                'digits_between:5,10',
                'unique:productores,carnet_identidad',
            ],

            'ci_extension' => [
                'required',
                'in:LP,CB,SC,OR,PT,CH,TJ,BE,PD',
            ],

            'complemento_ci' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/',
            ],

            'telefono' => [
                'nullable',
                'digits:8',
            ],

            'sexo' => [
                'nullable',
                'in:M,F,Otro',
            ],

            'fecha_nacimiento' => [
                'nullable',
                'date',
                'after_or_equal:1900-01-01',
                'before_or_equal:'.now()->subYears(18)->toDateString(),
            ],

            'direccion' => [
                'nullable',
                'string',
                'min:3',
                'max:255',
            ],

            'superficie_total_ha' => [
                'nullable',
                'numeric',
                'min:0',
                'max:5000',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_comunidad.required' => 'Debe seleccionar una comunidad.',
            'id_comunidad.exists' => 'La comunidad seleccionada no existe.',

            'nombres.min' => 'El nombre debe tener al menos 3 caracteres.',
            'apellido_paterno.min' => 'El apellido paterno debe tener al menos 3 caracteres.',
            'apellido_materno.min' => 'El apellido materno debe tener al menos 3 caracteres.',

            'nombres.required' => 'Los nombres del productor son obligatorios.',
            'nombres.regex' => 'Los nombres solo deben contener letras y espacios.',
            'nombres.max' => 'Los nombres no deben superar los 100 caracteres.',

            'apellido_paterno.required' => 'El apellido paterno es obligatorio.',
            'apellido_paterno.regex' => 'El apellido paterno solo debe contener letras y espacios.',
            'apellido_paterno.max' => 'El apellido paterno no debe superar los 100 caracteres.',

            'apellido_materno.regex' => 'El apellido materno solo debe contener letras y espacios.',
            'apellido_materno.max' => 'El apellido materno no debe superar los 100 caracteres.',

            'carnet_identidad.required' => 'El carnet de identidad es obligatorio.',
            'carnet_identidad.digits_between' => 'El carnet de identidad debe tener entre 5 y 10 dígitos.',
            'carnet_identidad.unique' => 'Ya existe un productor con este carnet de identidad.',

            'ci_extension.required' => 'Debe seleccionar la extensión del CI.',
            'ci_extension.in' => 'La extensión del CI no es válida.',

            'complemento_ci.regex' => 'El complemento del CI solo debe contener letras y números.',

            'complemento_ci.max' => 'El complemento del CI no debe superar los 10 caracteres.',

            'telefono.digits' => 'El teléfono debe tener exactamente 8 dígitos numéricos.',

            'sexo.in' => 'El sexo debe ser M, F u Otro.',

            'fecha_nacimiento.date' => 'La fecha de nacimiento no tiene un formato válido.',
            'fecha_nacimiento.after_or_equal' => 'La fecha de nacimiento no puede ser anterior al año 1900.',
            'fecha_nacimiento.before_or_equal' => 'El productor debe ser mayor de edad. Debe tener al menos 18 años.',

            'direccion.max' => 'La dirección no debe superar los 255 caracteres.',

            'superficie_total_ha.numeric' => 'La superficie total debe ser un número.',
            'superficie_total_ha.min' => 'La superficie total debe estar entre 0 y 5000 ha.',
            'superficie_total_ha.max' => 'La superficie total debe estar entre 0 y 5000 ha.',
        ];
    }
}
