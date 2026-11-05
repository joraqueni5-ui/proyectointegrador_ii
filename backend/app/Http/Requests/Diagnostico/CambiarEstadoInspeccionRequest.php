<?php

namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoInspeccionRequest extends FormRequest
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
                'in:Pendiente,En_proceso,Completada,Validada,No_realizada',
            ],

            'motivo_no_realizada' => [
                'required_if:estado,No_realizada',
                'nullable',
                'string',
                'max:1000',
            ],

            'fecha_reprogramada' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],

            'recomendacion_reprogramacion' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Debe seleccionar un estado.',
            'estado.in' => 'El estado de la inspección no es válido.',

            'motivo_no_realizada.required_if' => 'Debe registrar el motivo cuando la inspección no fue realizada.',
            'motivo_no_realizada.max' => 'El motivo no debe superar los 1000 caracteres.',

            'fecha_reprogramada.date' => 'La fecha de reprogramación no es válida.',
            'fecha_reprogramada.after_or_equal' => 'La fecha de reprogramación no puede ser anterior a hoy.',

            'recomendacion_reprogramacion.max' => 'La recomendación no debe superar los 1000 caracteres.',
        ];
    }
}
