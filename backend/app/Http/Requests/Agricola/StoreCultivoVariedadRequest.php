<?php

namespace App\Http\Requests\Agricola;

use App\Models\CultivoVariedad;
use App\Models\Parcela;
use Illuminate\Foundation\Http\FormRequest;

class StoreCultivoVariedadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'variedad_papa' => $this->variedad_papa
                ? trim((string) $this->variedad_papa)
                : null,

            'procedencia_semilla' => $this->procedencia_semilla
                ? trim((string) $this->procedencia_semilla)
                : null,

            'observaciones' => $this->observaciones
                ? trim((string) $this->observaciones)
                : null,

            'estado' => $this->estado ?: 'Activo',
        ]);
    }

    public function rules(): array
    {
        return [
            'id_parcela' => [
                'required',
                'exists:parcelas,id',
                function ($attribute, $value, $fail) {
                    $activa = Parcela::where('id', $value)
                        ->where('estado', 'Activa')
                        ->exists();

                    if (! $activa) {
                        $fail('No se puede registrar un cultivo en una parcela inactiva o en descanso.');
                    }
                },
                function ($attribute, $value, $fail) {
                    $existeActivo = CultivoVariedad::where('id_parcela', $value)
                        ->where('estado', 'Activo')
                        ->exists();

                    if ($existeActivo) {
                        $fail('La parcela seleccionada ya tiene un cultivo activo registrado.');
                    }
                },
            ],

            'variedad_papa' => [
                'required',
                'string',
                'max:120',
            ],

            'fecha_siembra' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'cantidad_semilla_kg' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:100000',
            ],

            'procedencia_semilla' => [
                'nullable',
                'string',
                'max:150',
            ],

            'id_etapa_fenologica' => [
                'nullable',
                'exists:etapas_fenologicas,id',
            ],

            'fecha_estimada_cosecha' => [
                'nullable',
                'date',
                'after_or_equal:fecha_siembra',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],

            // Un cultivo nuevo siempre empieza activo.
            'estado' => [
                'nullable',
                'in:Activo',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_parcela.required' => 'Debe seleccionar una parcela.',
            'id_parcela.exists' => 'La parcela seleccionada no existe.',

            'variedad_papa.required' => 'La variedad de papa es obligatoria.',
            'variedad_papa.max' => 'La variedad de papa no debe superar los 120 caracteres.',

            'fecha_siembra.required' => 'La fecha de siembra es obligatoria.',
            'fecha_siembra.date' => 'La fecha de siembra no tiene un formato válido.',
            'fecha_siembra.before_or_equal' => 'La fecha de siembra no puede ser futura.',

            'cantidad_semilla_kg.numeric' => 'La cantidad de semilla debe ser un número.',
            'cantidad_semilla_kg.min' => 'La cantidad de semilla debe ser mayor a 0.',
            'cantidad_semilla_kg.max' => 'La cantidad de semilla no puede superar los 100.000 kg.',

            'procedencia_semilla.max' => 'La procedencia de la semilla no debe superar los 150 caracteres.',

            'id_etapa_fenologica.exists' => 'La etapa fenológica seleccionada no existe.',

            'fecha_estimada_cosecha.date' => 'La fecha estimada de cosecha no tiene un formato válido.',
            'fecha_estimada_cosecha.after_or_equal' => 'La fecha estimada de cosecha debe ser igual o posterior a la fecha de siembra.',

            'estado.in' => 'El estado del cultivo no es válido.',
        ];
    }
}
