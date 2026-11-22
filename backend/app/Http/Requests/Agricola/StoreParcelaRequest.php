<?php

namespace App\Http\Requests\Agricola;

use App\Models\Productor;
use Illuminate\Foundation\Http\FormRequest;

class StoreParcelaRequest extends FormRequest
{
    use ValidaSuperficieParcela;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo_parcela' => $this->codigo_parcela
                ? strtoupper(trim((string) $this->codigo_parcela))
                : null,

            'nombre_referencial' => $this->nombre_referencial
                ? trim((string) $this->nombre_referencial)
                : null,

            'coordenadas_texto' => $this->coordenadas_texto
                ? trim((string) $this->coordenadas_texto)
                : null,

            'tipo_suelo' => $this->tipo_suelo ?: null,
            'pendiente' => $this->pendiente ?: null,
            'sistema_riego' => $this->sistema_riego ?: 'Secano',
            'estado' => $this->estado ?: 'Activa',

            'observaciones' => $this->observaciones
                ? trim((string) $this->observaciones)
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id_productor' => [
                'required',
                'exists:productores,id',
                function ($attribute, $value, $fail) {
                    $activo = Productor::where('id', $value)
                        ->where('activo', true)
                        ->exists();

                    if (! $activo) {
                        $fail('No se puede registrar una parcela para un productor inactivo.');
                    }
                },
            ],

            'codigo_parcela' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9\-]+$/',
                'unique:parcelas,codigo_parcela',
            ],

            // Obligatorio en parcelas nuevas (las anteriores sin nombre se siguen pudiendo ver y editar).
            'nombre_referencial' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],

            'latitud' => [
                'required',
                'numeric',
                'between:-23,-9',
            ],

            'longitud' => [
                'required',
                'numeric',
                'between:-70,-57',
            ],

            'coordenadas_texto' => [
                'nullable',
                'string',
                'max:100',
            ],

            'altitud_msnm' => [
                'nullable',
                'integer',
                'between:0,6600',
            ],

            'superficie_ha' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:500',
                'decimal:0,2',
            ],

            'tipo_suelo' => [
                'nullable',
                'in:Arcilloso,Limoso,Arenoso,Franco,Franco-arcilloso,Otro',
            ],

            'pendiente' => [
                'nullable',
                'in:Plano,Suave,Moderado,Fuerte',
            ],

            'sistema_riego' => [
                'required',
                'in:Secano,Riego,Mixto',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],

            'estado' => [
                'nullable',
                'in:Activa,Inactiva,En_descanso',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_referencial.required' => 'Escribe un nombre para reconocer la parcela (por ejemplo "Lote Cruz Pata").',
            'id_productor.required' => 'Debe seleccionar un productor.',
            'id_productor.exists' => 'El productor seleccionado no existe.',

            'codigo_parcela.required' => 'El código de parcela es obligatorio.',
            'codigo_parcela.unique' => 'Ya existe una parcela con este código.',
            'codigo_parcela.max' => 'El código de parcela no debe superar los 30 caracteres.',
            'codigo_parcela.regex' => 'El código de parcela solo debe contener letras, números y guiones. Ejemplo: P-SUR-01.',

            'nombre_referencial.max' => 'El nombre referencial no debe superar los 150 caracteres.',
            'nombre_referencial.min' => 'El nombre referencial debe ser mayor a 2 caracteres.',

            'latitud.required' => 'La latitud es obligatoria para ubicar la parcela en el mapa.',
            'latitud.numeric' => 'La latitud debe ser un número.',
            'latitud.between' => 'La latitud debe estar dentro de Bolivia (entre -23 y -9).',

            'longitud.required' => 'La longitud es obligatoria para ubicar la parcela en el mapa.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'longitud.between' => 'La longitud debe estar dentro de Bolivia (entre -70 y -57).',

            'coordenadas_texto.max' => 'Las coordenadas en texto no deben superar los 100 caracteres.',

            'altitud_msnm.integer' => 'La altitud debe ser un número entero.',
            'altitud_msnm.between' => 'La altitud debe estar entre 0 y 6600 msnm.',

            'superficie_ha.numeric' => 'La superficie debe ser un número.',
            'superficie_ha.min' => 'La superficie debe estar entre 0,01 y 500 ha.',
            'superficie_ha.max' => 'La superficie debe estar entre 0,01 y 500 ha.',
            'superficie_ha.decimal' => 'La superficie admite como máximo 2 decimales.',

            'tipo_suelo.in' => 'El tipo de suelo seleccionado no es válido.',
            'pendiente.in' => 'La pendiente seleccionada no es válida.',

            'sistema_riego.required' => 'Debe seleccionar el sistema de riego.',
            'sistema_riego.in' => 'El sistema de riego seleccionado no es válido.',

            'estado.in' => 'El estado de la parcela no es válido.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $this->validarSuperficieContraTotal($validator, null);
        });
    }
}
