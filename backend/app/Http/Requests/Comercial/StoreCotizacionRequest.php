<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;

class StoreCotizacionRequest extends FormRequest
{
    use ValidaDetallesComerciales;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->reglasDetalles();
    }

    public function messages(): array
    {
        return $this->mensajesDetalles();
    }
}
