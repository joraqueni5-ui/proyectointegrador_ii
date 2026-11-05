<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    use ValidaDetallesComerciales;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->reglasDetalles() + [
            // Las devoluciones se registran anulando la venta (repone stock);
            // una venta nueva siempre descuenta inventario.
            'tipo' => ['required', 'in:Venta'],
            'estado' => ['nullable', 'in:Pendiente,Pagada,Parcial'],
        ];
    }

    public function messages(): array
    {
        return $this->mensajesDetalles() + [
            'tipo.required' => 'Debe seleccionar el tipo de venta.',
            'tipo.in' => 'El tipo de venta no es válido.',
            'estado.in' => 'El estado inicial de la venta no es válido.',
        ];
    }
}
