<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;

class StoreMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_producto' => ['required', 'exists:productos,id'],
            'id_almacen' => ['required', 'exists:almacenes,id'],
            'tipo' => ['required', 'in:Entrada,Salida'],
            'cantidad' => ['required', 'numeric', 'min:0.001'],
            'motivo' => [
                'required',
                'in:Compra,Venta,Devolucion,Ajuste_entrada,Ajuste_salida,Traslado_entrada,Traslado_salida,Otro',
            ],
            'observaciones' => ['nullable', 'string'],
            'fecha_movimiento' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_producto.required' => 'Debe seleccionar un producto.',
            'id_producto.exists' => 'El producto seleccionado no existe.',

            'id_almacen.required' => 'Debe seleccionar un almacén.',
            'id_almacen.exists' => 'El almacén seleccionado no existe.',

            'tipo.required' => 'Debe seleccionar el tipo de movimiento.',
            'tipo.in' => 'El tipo de movimiento debe ser Entrada o Salida.',

            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.numeric' => 'La cantidad debe ser numérica.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',

            'motivo.required' => 'Debe seleccionar el motivo.',
            'motivo.in' => 'El motivo seleccionado no es válido.',

            'fecha_movimiento.before_or_equal' => 'La fecha del movimiento no puede ser futura.',
        ];
    }
}
