<?php

namespace App\Http\Requests\Diagnostico;

use App\Models\Inspeccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDiagnosticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->tieneAlgunoDeEstosPermisos(['crear_diagnosticos']);
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'No tienes permiso para registrar diagnósticos.');
    }

    public function rules(): array
    {
        return [
            'id_inspeccion' => [
                'required',
                'exists:inspecciones,id',
            ],

            'fotografia' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                // Mismo mínimo que exige el servicio CNN (160 × 160 px).
                'dimensions:min_width=160,min_height=160',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $inspeccion = Inspeccion::with([
                    'diagnosticos',
                    'cultivo:id,id_parcela,estado',
                ])->find($this->input('id_inspeccion'));

                if (! $inspeccion) {
                    return;
                }

                $tipoInspeccion = $inspeccion->tipo_inspeccion ?? 'Cultivo';

                if ($tipoInspeccion !== 'Cultivo') {
                    $validator->errors()->add(
                        'id_inspeccion',
                        'Solo se pueden registrar diagnósticos CNN en inspecciones de cultivo.'
                    );
                }

                if (! $inspeccion->id_cultivo) {
                    $validator->errors()->add(
                        'id_inspeccion',
                        'La inspección seleccionada no tiene un cultivo asociado.'
                    );
                }

                if ($inspeccion->cultivo && $inspeccion->cultivo->estado !== 'Activo') {
                    $validator->errors()->add(
                        'id_inspeccion',
                        'El cultivo asociado a la inspección ya no se encuentra activo.'
                    );
                }

                if (
                    $inspeccion->cultivo &&
                    (int) $inspeccion->cultivo->id_parcela !== (int) $inspeccion->id_parcela
                ) {
                    $validator->errors()->add(
                        'id_inspeccion',
                        'El cultivo asociado no corresponde a la parcela de la inspección.'
                    );
                }

                $mensajeEstado = match ($inspeccion->estado) {
                    'En_proceso', 'Completada' => null,
                    'Pendiente' => 'Primero debe iniciar la inspección antes de registrar un diagnóstico.',
                    'No_realizada' => 'No se puede diagnosticar una inspección marcada como no realizada.',
                    'Validada' => 'No se puede diagnosticar una inspección ya validada.',
                    default => 'La inspección debe estar en proceso o completada para registrar el diagnóstico.',
                };

                if ($mensajeEstado) {
                    $validator->errors()->add('id_inspeccion', $mensajeEstado);
                }

                $tieneDiagnosticoPendiente = $inspeccion->diagnosticos()
                    ->where('estado_validacion', 'Pendiente')
                    ->exists();

                if ($tieneDiagnosticoPendiente) {
                    $validator->errors()->add(
                        'id_inspeccion',
                        'Esta inspección ya tiene un diagnóstico pendiente de validación.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'id_inspeccion.required' => 'Debe seleccionar una inspección.',
            'id_inspeccion.exists' => 'La inspección seleccionada no existe.',
            'fotografia.required' => 'Debe subir una fotografía.',
            'fotografia.image' => 'El archivo debe ser una imagen.',
            'fotografia.mimes' => 'La imagen debe ser JPG, JPEG, PNG o WEBP.',
            'fotografia.max' => 'La imagen no debe superar los 5 MB.',
            'fotografia.dimensions' => 'La imagen es muy pequeña: debe medir al menos 160 × 160 píxeles. Acérquese a la hoja o use una foto de mayor resolución.',
            'fotografia.uploaded' => 'No se pudo subir la fotografía. Verifique que no supere los 5 MB e inténtelo de nuevo.',
        ];
    }
}
