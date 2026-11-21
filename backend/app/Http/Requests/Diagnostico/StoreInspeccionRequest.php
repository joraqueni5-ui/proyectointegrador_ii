<?php

namespace App\Http\Requests\Diagnostico;

use App\Models\CultivoVariedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInspeccionRequest extends FormRequest
{
    use ValidaTecnicoDisponible;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_usuario' => $this->id_usuario === '' ? null : $this->id_usuario,
            'id_cultivo' => $this->id_cultivo === '' ? null : $this->id_cultivo,
            'id_etapa_fenologica' => $this->id_etapa_fenologica === '' ? null : $this->id_etapa_fenologica,

            'tipo_inspeccion' => $this->tipo_inspeccion ?: 'Cultivo',

            'hora_visita' => $this->hora_visita === '' ? null : $this->hora_visita,
            'condicion_climatica' => $this->condicion_climatica === '' ? null : $this->condicion_climatica,
            'temperatura_c' => $this->temperatura_c === '' ? null : $this->temperatura_c,
            'humedad_relativa_pct' => $this->humedad_relativa_pct === '' ? null : $this->humedad_relativa_pct,
            'pct_plantas_afectadas' => $this->pct_plantas_afectadas === '' ? null : $this->pct_plantas_afectadas,

            'observaciones' => $this->observaciones ? trim((string) $this->observaciones) : null,
            'condicion_terreno' => $this->condicion_terreno ? trim((string) $this->condicion_terreno) : null,
            'recomendacion_pre_siembra' => $this->recomendacion_pre_siembra ? trim((string) $this->recomendacion_pre_siembra) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            // Técnico asignado a la inspección
            'id_usuario' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('activo', true),
            ],

            'id_parcela' => [
                'required',
                'integer',
                Rule::exists('parcelas', 'id')->where(fn ($q) => $q->where('estado', '!=', 'Inactiva')),
            ],

            'tipo_inspeccion' => [
                'required',
                'in:Cultivo,Pre_siembra',
            ],

            'id_cultivo' => [
                'nullable',
                'integer',
                'exists:cultivo_variedades,id',
            ],

            'id_etapa_fenologica' => [
                'nullable',
                'integer',
                'exists:etapas_fenologicas,id',
            ],

            'fecha_visita' => [
                'required',
                'date',
            ],

            'hora_visita' => [
                'nullable',
                'date_format:H:i',
            ],

            'condicion_climatica' => [
                'nullable',
                'in:Soleado,Nublado,Lluvioso,Helado,Ventoso,Mixto',
            ],

            'temperatura_c' => [
                'nullable',
                'numeric',
                'between:-30,50',
            ],

            'humedad_relativa_pct' => [
                'nullable',
                'integer',
                'between:0,100',
            ],

            'pct_plantas_afectadas' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],

            // Validada y No_realizada se asignan después, desde el cambio de estado.
            'estado' => [
                'nullable',
                'in:Pendiente,En_proceso,Completada',
            ],

            'condicion_terreno' => [
                'nullable',
                'string',
                'max:80',
            ],

            'recomendacion_pre_siembra' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validarTecnicoDisponible($validator, null));

        $validator->after(function (Validator $validator) {
            $tipoInspeccion = $this->input('tipo_inspeccion', 'Cultivo');
            $idCultivo = $this->input('id_cultivo');
            $idParcela = $this->input('id_parcela');
            $fechaVisita = $this->input('fecha_visita');

            if ($tipoInspeccion === 'Cultivo' && ! $idCultivo) {
                $validator->errors()->add(
                    'id_cultivo',
                    'Debe seleccionar un cultivo activo para una inspección de tipo cultivo.'
                );

                return;
            }

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

            if ($tipoInspeccion === 'Cultivo' && $cultivo && $cultivo->estado !== 'Activo') {
                $validator->errors()->add(
                    'id_cultivo',
                    'Solo se pueden registrar inspecciones sobre cultivos activos.'
                );
            }

            if (
                $cultivo &&
                $cultivo->fecha_siembra &&
                $fechaVisita &&
                $fechaVisita < $cultivo->fecha_siembra->toDateString()
            ) {
                $validator->errors()->add(
                    'fecha_visita',
                    'La fecha de inspección no puede ser anterior a la fecha de siembra del cultivo.'
                );
            }

            $estado = $this->input('estado', 'Pendiente');

            if (
                in_array($estado, ['En_proceso', 'Completada', 'Validada']) &&
                $fechaVisita &&
                $fechaVisita > now()->toDateString()
            ) {
                $validator->errors()->add(
                    'fecha_visita',
                    'La fecha de visita no puede ser futura cuando la inspección está en proceso, completada o validada.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'id_usuario.required' => 'Debe seleccionar el técnico asignado.',
            'id_usuario.exists' => 'El técnico asignado no existe o está inactivo.',

            'id_parcela.required' => 'Debe seleccionar una parcela.',
            'id_parcela.exists' => 'La parcela seleccionada no existe o está inactiva.',

            'tipo_inspeccion.required' => 'Debe seleccionar el tipo de inspección.',
            'tipo_inspeccion.in' => 'El tipo de inspección no es válido.',

            'id_cultivo.exists' => 'El cultivo seleccionado no existe.',
            'id_etapa_fenologica.exists' => 'La etapa fenológica seleccionada no existe.',

            'fecha_visita.required' => 'La fecha de visita es obligatoria.',
            'fecha_visita.date' => 'La fecha de visita no tiene un formato válido.',

            'hora_visita.date_format' => 'La hora debe tener el formato HH:mm.',

            'condicion_climatica.in' => 'La condición climática seleccionada no es válida.',

            'temperatura_c.numeric' => 'La temperatura debe ser un número.',
            'temperatura_c.between' => 'La temperatura debe estar entre -30 y 50 °C.',

            'humedad_relativa_pct.integer' => 'La humedad relativa debe ser un número entero.',
            'humedad_relativa_pct.between' => 'La humedad relativa debe estar entre 0 y 100%.',

            'pct_plantas_afectadas.numeric' => 'El porcentaje de plantas afectadas debe ser un número.',
            'pct_plantas_afectadas.between' => 'El porcentaje de plantas afectadas debe estar entre 0 y 100%.',

            'estado.in' => 'El estado de la inspección no es válido.',

            'condicion_terreno.max' => 'La condición del terreno no debe superar los 80 caracteres.',
            'recomendacion_pre_siembra.max' => 'La recomendación de pre siembra no debe superar los 1000 caracteres.',
        ];
    }
}
