<?php

namespace App\Http\Requests\Agricola;

use App\Models\Cosecha;
use App\Models\CultivoVariedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCosechaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_cultivo' => $this->id_cultivo === '' ? null : $this->id_cultivo,

            'calidad' => $this->calidad ?: 'Primera',
            'destino' => $this->destino ?: 'Venta',

            'observaciones' => $this->observaciones
                ? trim((string) $this->observaciones)
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id_parcela' => [
                'required',
                'integer',
                'exists:parcelas,id',
            ],

            'id_cultivo' => [
                'nullable',
                'integer',
                'exists:cultivo_variedades,id',
            ],

            'fecha_cosecha' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'cantidad_toneladas' => [
                'required',
                'numeric',
                'min:0.001',
                'max:10000',
                'decimal:0,3',
            ],

            'calidad' => [
                'required',
                'in:Primera,Segunda,Tercera,Descarte',
            ],

            'destino' => [
                'required',
                'in:Venta,Semilla,Autoconsumo,Mixto',
            ],

            'precio_unitario_bs' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'ingreso_estimado_bs' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $idCultivo = $this->input('id_cultivo');
            $idParcela = $this->input('id_parcela');

            if (! $idCultivo || ! $idParcela) {
                return;
            }

            $cultivo = CultivoVariedad::find($idCultivo);

            if ($cultivo && (int) $cultivo->id_parcela !== (int) $idParcela) {
                $validator->errors()->add(
                    'id_cultivo',
                    'El cultivo seleccionado no pertenece a la parcela indicada.'
                );
            }

            if ($cultivo && $cultivo->estado !== 'Activo') {
                $validator->errors()->add(
                    'id_cultivo',
                    'Solo se puede registrar una cosecha para cultivos activos.'
                );
            }

            if ($cultivo && Cosecha::where('id_cultivo', $cultivo->id)->exists()) {
                $validator->errors()->add(
                    'id_cultivo',
                    'Este cultivo ya tiene una cosecha registrada.'
                );
            }

            if ($cultivo && $this->input('fecha_cosecha') < optional($cultivo->fecha_siembra)->toDateString()) {
                $validator->errors()->add(
                    'fecha_cosecha',
                    'La fecha de cosecha no puede ser anterior a la fecha de siembra del cultivo.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'id_parcela.required' => 'Debe seleccionar una parcela.',
            'id_parcela.exists' => 'La parcela seleccionada no existe.',

            'id_cultivo.exists' => 'El cultivo seleccionado no existe.',

            'fecha_cosecha.required' => 'La fecha de cosecha es obligatoria.',
            'fecha_cosecha.date' => 'La fecha de cosecha no tiene un formato válido.',
            'fecha_cosecha.before_or_equal' => 'La fecha de cosecha no puede ser futura.',

            'cantidad_toneladas.required' => 'La cantidad cosechada es obligatoria.',
            'cantidad_toneladas.numeric' => 'La cantidad cosechada debe ser un número.',
            'cantidad_toneladas.min' => 'La cantidad cosechada debe ser mayor a 0.',
            'cantidad_toneladas.max' => 'La cantidad cosechada no puede superar las 10.000 toneladas.',
            'cantidad_toneladas.decimal' => 'La cantidad cosechada admite como máximo 3 decimales.',

            'calidad.required' => 'Debe seleccionar la calidad.',
            'calidad.in' => 'La calidad seleccionada no es válida.',

            'destino.required' => 'Debe seleccionar el destino.',
            'destino.in' => 'El destino seleccionado no es válido.',

            'precio_unitario_bs.numeric' => 'El precio unitario debe ser un número.',
            'precio_unitario_bs.min' => 'El precio unitario no puede ser negativo.',

            'ingreso_estimado_bs.numeric' => 'El ingreso estimado debe ser un número.',
            'ingreso_estimado_bs.min' => 'El ingreso estimado no puede ser negativo.',
        ];
    }
}
