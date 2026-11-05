<?php

namespace App\Http\Requests\Diagnostico;

use App\Models\CultivoVariedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInspeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Solo se normalizan los campos enviados: los ausentes no deben llegar como null.
        $this->merge(array_intersect_key([
            'id_usuario' => $this->id_usuario === '' ? null : $this->id_usuario,
            'id_cultivo' => $this->id_cultivo === '' ? null : $this->id_cultivo,
            'id_etapa_fenologica' => $this->id_etapa_fenologica === '' ? null : $this->id_etapa_fenologica,

            'hora_visita' => $this->hora_visita === '' ? null : $this->hora_visita,
            'condicion_climatica' => $this->condicion_climatica === '' ? null : $this->condicion_climatica,
            'temperatura_c' => $this->temperatura_c === '' ? null : $this->temperatura_c,
            'humedad_relativa_pct' => $this->humedad_relativa_pct === '' ? null : $this->humedad_relativa_pct,
            'pct_plantas_afectadas' => $this->pct_plantas_afectadas === '' ? null : $this->pct_plantas_afectadas,

            'observaciones' => $this->observaciones ? trim((string) $this->observaciones) : null,
            'condicion_terreno' => $this->condicion_terreno ? trim((string) $this->condicion_terreno) : null,
            'recomendacion_pre_siembra' => $this->recomendacion_pre_siembra ? trim((string) $this->recomendacion_pre_siembra) : null,
        ], $this->all()));
    }

    public function rules(): array
    {
        return [
            'id_usuario' => [
                'sometimes',
                'required',
                'integer',
                'exists:users,id',
            ],

            'id_parcela' => [
                'sometimes',
                'required',
                'integer',
                'exists:parcelas,id',
            ],

            'tipo_inspeccion' => [
                'sometimes',
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
                'sometimes',
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
        $validator->after(function (Validator $validator) {
            $inspeccion = $this->route('inspeccion');

            $tipoInspeccion = $this->input(
                'tipo_inspeccion',
                $inspeccion?->tipo_inspeccion ?? 'Cultivo'
            );

            $idCultivo = $this->input('id_cultivo', $inspeccion?->id_cultivo);
            $idParcela = $this->input('id_parcela', $inspeccion?->id_parcela);
            $fechaVisita = $this->input(
                'fecha_visita',
                $inspeccion?->fecha_visita?->toDateString()
            );

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

            $estado = $inspeccion?->estado ?? 'Pendiente';

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
        return (new StoreInspeccionRequest)->messages();
    }
}
