<?php

namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidarDiagnosticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->tieneAlgunoDeEstosPermisos(['validar_diagnosticos']);
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Solo un agrónomo o un administrador puede validar diagnósticos.');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_enfermedad' => $this->id_enfermedad === '' ? null : $this->id_enfermedad,
            'nivel_severidad' => $this->nivel_severidad === '' ? null : $this->nivel_severidad,
            'observaciones_validacion' => $this->observaciones_validacion
                ? trim((string) $this->observaciones_validacion)
                : null,
            'pct_plantas_afectadas' => $this->pct_plantas_afectadas === ''
                ? null
                : $this->pct_plantas_afectadas,
        ]);
    }

    public function rules(): array
    {
        return [
            'estado_validacion' => [
                'required',
                'in:Confirmado,Corregido,Rechazado',
            ],

            'id_enfermedad' => [
                'nullable',
                'required_if:estado_validacion,Corregido',
                Rule::exists('enfermedades', 'id')->where(fn ($query) => $query->where('activo', true)),
            ],

            'nivel_severidad' => [
                'required_unless:estado_validacion,Rechazado',
                'nullable',
                'in:Sin_afeccion,Leve,Moderada,Severa,Muy_severa',
            ],

            'pct_plantas_afectadas' => [
                'required_unless:estado_validacion,Rechazado',
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'observaciones_validacion' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $diagnostico = $this->route('diagnostico');

                if (! $diagnostico) {
                    return;
                }

                if ($diagnostico->estado_validacion !== 'Pendiente') {
                    $validator->errors()->add(
                        'estado_validacion',
                        'Este diagnóstico ya fue validado anteriormente.'
                    );
                }

                if (
                    $this->input('estado_validacion') === 'Corregido' &&
                    (int) $this->input('id_enfermedad') === (int) $diagnostico->id_enfermedad
                ) {
                    $validator->errors()->add(
                        'id_enfermedad',
                        'Si la enfermedad no cambia, utilice la opción Confirmado en lugar de Corregido.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'estado_validacion.required' => 'Debe seleccionar el estado de validación.',
            'estado_validacion.in' => 'El estado de validación no es válido.',

            'id_enfermedad.required_if' => 'Debe seleccionar la enfermedad correcta cuando el diagnóstico es corregido.',
            'id_enfermedad.exists' => 'La enfermedad seleccionada no existe.',
            'nivel_severidad.in' => 'El nivel de severidad seleccionado no es válido.',

            'observaciones_validacion.string' => 'Las observaciones deben ser texto.',
            'observaciones_validacion.max' => 'Las observaciones no deben superar los 1000 caracteres.',

            'nivel_severidad.required_unless' => 'Debe registrar la severidad observada al confirmar o corregir un diagnóstico.',

            'pct_plantas_afectadas.required_unless' => 'Debe registrar el porcentaje estimado de plantas afectadas.',

            'pct_plantas_afectadas.numeric' => 'El porcentaje de plantas afectadas debe ser un número.',

            'pct_plantas_afectadas.between' => 'El porcentaje de plantas afectadas debe estar entre 0 y 100.',
        ];
    }
}
