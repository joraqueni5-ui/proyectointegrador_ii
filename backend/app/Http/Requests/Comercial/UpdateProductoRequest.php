<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $producto = $this->route('producto');
        $productoId = is_object($producto) ? $producto->id : $producto;

        return [
            'codigo_producto' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('productos', 'codigo_producto')->ignore($productoId),
            ],

            'nombre_producto' => ['sometimes', 'string', 'max:200'],
            'categoria' => ['sometimes', 'in:Semilla,Bioinsumo,Agroquimico,Fertilizante,Equipo,Otro'],
            'descripcion' => ['nullable', 'string'],
            'unidad_medida' => ['sometimes', 'string', 'max:30'],
            'precio_unitario' => ['sometimes', 'numeric', 'min:0'],
            'precio_costo' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'imagen_url' => ['nullable', 'string', 'max:500'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo_producto.unique' => 'Ya existe un producto con este código.',
            'codigo_producto.max' => 'El código no debe superar los 30 caracteres.',

            'nombre_producto.max' => 'El nombre no debe superar los 200 caracteres.',
            'categoria.in' => 'La categoría seleccionada no es válida.',
            'unidad_medida.max' => 'La unidad de medida no debe superar los 30 caracteres.',

            'precio_unitario.numeric' => 'El precio unitario debe ser numérico.',
            'precio_unitario.min' => 'El precio unitario no puede ser negativo.',

            'precio_costo.numeric' => 'El precio de costo debe ser numérico.',
            'precio_costo.min' => 'El precio de costo no puede ser negativo.',

            'stock_minimo.numeric' => 'El stock mínimo debe ser numérico.',
            'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',

            'imagen_url.max' => 'La URL de imagen no debe superar los 500 caracteres.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
