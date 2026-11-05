<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->name),
            'paterno' => $this->paterno ? trim((string) $this->paterno) : null,
            'materno' => $this->materno ? trim((string) $this->materno) : null,

            'ci' => $this->ci ? preg_replace('/\D/', '', (string) $this->ci) : null,
            'ci_extension' => $this->ci_extension ? strtoupper(trim((string) $this->ci_extension)) : null,
            'sexo' => $this->sexo ? trim((string) $this->sexo) : null,

            'email' => strtolower(trim((string) $this->email)),
            'celular' => $this->celular ? preg_replace('/\D/', '', (string) $this->celular) : null,

            'cargo' => $this->cargo ? trim((string) $this->cargo) : null,
            'area' => $this->area ? trim((string) $this->area) : null,
            'oficina_regional' => $this->oficina_regional ? trim((string) $this->oficina_regional) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:80',
                'regex:/^[\pL\s]+$/u',
            ],

            'paterno' => [
                'required',
                'string',
                'min:3',
                'max:80',
                'regex:/^[\pL\s]+$/u',
            ],

            'materno' => [
                'nullable',
                'string',
                'min:3',
                'max:80',
                'regex:/^[\pL\s]+$/u',
            ],

            'ci' => [
                'required',
                'digits_between:5,10',
                'unique:users,ci',
            ],

            'ci_extension' => [
                'nullable',
                'in:LP,CB,SC,OR,PT,CH,TJ,BE,PD',
            ],

            'sexo' => [
                'required',
                'in:Masculino,Femenino,Otro,Prefiero no decir',
            ],

            'email' => [
                'required',
                'email',
                'max:120',
                'unique:users,email',
            ],

            'celular' => [
                'nullable',
                'digits:8',
            ],

            'cargo' => [
                'required',
                'string',
                'max:100',
            ],

            'area' => [
                'required',
                'string',
                'max:100',
            ],

            'oficina_regional' => [
                'nullable',
                'string',
                'max:100',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],

            'id_rol' => [
                'required',
                'exists:roles,id',
            ],

            'activo' => [
                'nullable',
                'boolean',
            ],

            'face_photo' => [
                'nullable',
                'string',
            ],

            'face_descriptor' => [
                'nullable',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.regex' => 'El nombre solo debe contener letras y espacios.',
            'name.max' => 'El nombre no debe superar los 80 caracteres.',

            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'paterno.min' => 'El apellido paterno debe tener al menos 3 caracteres.',
            'materno.min' => 'El apellido materno debe tener al menos 3 caracteres.',

            'paterno.required' => 'El apellido paterno es obligatorio.',
            'paterno.regex' => 'El apellido paterno solo debe contener letras y espacios.',
            'paterno.max' => 'El apellido paterno no debe superar los 80 caracteres.',

            'materno.regex' => 'El apellido materno solo debe contener letras y espacios.',
            'materno.max' => 'El apellido materno no debe superar los 80 caracteres.',

            'ci.required' => 'El carnet de identidad es obligatorio.',
            'ci.digits_between' => 'El carnet de identidad debe tener entre 5 y 10 dígitos.',
            'ci.unique' => 'Este carnet de identidad ya está registrado.',

            'ci_extension.in' => 'La extensión del CI no es válida.',

            'sexo.required' => 'Debes seleccionar el sexo.',
            'sexo.in' => 'El valor seleccionado para sexo no es válido.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',

            'celular.digits' => 'El celular debe tener exactamente 8 dígitos numéricos.',

            'cargo.required' => 'El cargo es obligatorio.',
            'cargo.max' => 'El cargo no debe superar los 100 caracteres.',

            'area.required' => 'El área o unidad es obligatoria.',
            'area.max' => 'El área o unidad no debe superar los 100 caracteres.',

            'oficina_regional.max' => 'La oficina regional no debe superar los 100 caracteres.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',

            'id_rol.required' => 'Debes seleccionar un rol.',
            'id_rol.exists' => 'El rol seleccionado no existe.',
        ];
    }
}
