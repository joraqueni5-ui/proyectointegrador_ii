<?php

namespace App\Http\Requests\User;

use App\Rules\ContrasenaSegura;
use App\Rules\CorreoUnicoSinMayusculas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->tieneAlgunoDeEstosPermisos(['gestionar_usuarios']);
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'No tienes permiso para realizar esta acción.');
    }

    protected function prepareForValidation(): void
    {
        // Solo se normalizan los campos enviados: los ausentes no deben llegar como null.
        $this->merge(array_intersect_key([
            'name' => $this->name ? trim((string) $this->name) : $this->name,
            'paterno' => $this->paterno ? trim((string) $this->paterno) : $this->paterno,
            'materno' => $this->materno ? trim((string) $this->materno) : null,

            'ci' => $this->ci ? preg_replace('/\D/', '', (string) $this->ci) : $this->ci,
            'ci_extension' => $this->ci_extension ? strtoupper(trim((string) $this->ci_extension)) : null,
            'sexo' => $this->sexo ? trim((string) $this->sexo) : $this->sexo,

            'email' => $this->email ? strtolower(trim((string) $this->email)) : $this->email,
            'celular' => $this->celular ? preg_replace('/\D/', '', (string) $this->celular) : null,

            'cargo' => $this->cargo ? trim((string) $this->cargo) : $this->cargo,
            'area' => $this->area ? trim((string) $this->area) : $this->area,
            'oficina_regional' => $this->oficina_regional ? trim((string) $this->oficina_regional) : null,
        ], $this->all()));
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user')?->id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:80',
                'regex:/^[\pL\s]+$/u',
            ],

            'paterno' => [
                'sometimes',
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
                'sometimes',
                'required',
                'digits_between:5,10',
                Rule::unique('users', 'ci')->ignore($userId),
            ],

            'ci_extension' => [
                'sometimes',
                'required',
                'in:LP,CB,SC,OR,PT,CH,TJ,BE,PD',
            ],

            'sexo' => [
                'sometimes',
                'required',
                'in:Masculino,Femenino,Otro,Prefiero no decir',
            ],

            'email' => [
                'sometimes',
                'required',
                'email',
                'max:120',
                new CorreoUnicoSinMayusculas($userId ? (int) $userId : null),
            ],

            'celular' => [
                'sometimes',
                'required',
                'regex:/^[67]\d{7}$/',
            ],

            'cargo' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'area' => [
                'sometimes',
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
                'nullable',
                'string',
                new ContrasenaSegura,
            ],

            'id_rol' => [
                'sometimes',
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

            'ci_extension.required' => 'Selecciona la extensión del carnet (por ejemplo LP).',
            'ci_extension.in' => 'La extensión del CI no es válida.',

            'sexo.required' => 'Debes seleccionar el sexo.',
            'sexo.in' => 'El valor seleccionado para sexo no es válido.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',

            'celular.required' => 'El celular es obligatorio.',
            'celular.regex' => 'El celular debe tener 8 dígitos y empezar con 6 o 7 (por ejemplo 71234567).',

            'cargo.required' => 'El cargo es obligatorio.',
            'cargo.max' => 'El cargo no debe superar los 100 caracteres.',

            'area.required' => 'El área o unidad es obligatoria.',
            'area.max' => 'El área o unidad no debe superar los 100 caracteres.',

            'oficina_regional.max' => 'La oficina regional no debe superar los 100 caracteres.',


            'id_rol.required' => 'Debes seleccionar un rol.',
            'id_rol.exists' => 'El rol seleccionado no existe.',
        ];
    }
}
