<?php

namespace App\Http\Requests\Diagnostico;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros de la vista "Desempeño del modelo". Solo lectura.
 */
class DesempenoModeloRequest extends FormRequest
{
    /** Rango máximo de fechas permitido (días). */
    public const RANGO_MAXIMO_DIAS = 1095;

    public function authorize(): bool
    {
        return (bool) $this->user()?->tieneAlgunoDeEstosPermisos(['ver_diagnosticos']);
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'No tienes permiso para ver el desempeño del modelo.');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha_desde' => $this->texto('fecha_desde'),
            'fecha_hasta' => $this->texto('fecha_hasta'),
            'modelo_version' => $this->texto('modelo_version'),
        ]);
    }

    private function texto(string $campo): ?string
    {
        $valor = $this->input($campo);

        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    public function rules(): array
    {
        return [
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'modelo_version' => [
                'nullable', 'string', 'max:50',
                Rule::exists('diagnosticos_cnn', 'modelo_version'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $desde = $this->input('fecha_desde');
                $hasta = $this->input('fecha_hasta');

                if (
                    $desde && $hasta && ! $validator->errors()->has('fecha_hasta') && ! $validator->errors()->has('fecha_desde')
                    && Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) > self::RANGO_MAXIMO_DIAS
                ) {
                    $validator->errors()->add(
                        'fecha_hasta',
                        'El rango de fechas no puede superar '.self::RANGO_MAXIMO_DIAS.' días (3 años). Acorta el período.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            'modelo_version.max' => 'La versión del modelo no puede superar los 50 caracteres.',
            'modelo_version.exists' => 'La versión del modelo seleccionada no existe en los diagnósticos registrados.',
        ];
    }
}
