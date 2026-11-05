<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_producto' => ['required', 'string', 'max:30', 'unique:productos,codigo_producto'],
            'nombre_producto' => ['required', 'string', 'max:200'],
            'categoria' => ['required', 'in:Semilla,Bioinsumo,Agroquimico,Fertilizante,Equipo,Otro'],
            'descripcion' => ['nullable', 'string'],
            'unidad_medida' => ['required', 'string', 'max:30'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'precio_costo' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'imagen_url' => ['nullable', 'string', 'max:500'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo_producto.required' => 'El código del producto es obligatorio.',
            'codigo_producto.unique' => 'Ya existe un producto con este código.',
            'codigo_producto.max' => 'El código no debe superar los 30 caracteres.',

            'nombre_producto.required' => 'El nombre del producto es obligatorio.',
            'nombre_producto.max' => 'El nombre no debe superar los 200 caracteres.',

            'categoria.required' => 'Debe seleccionar una categoría.',
            'categoria.in' => 'La categoría seleccionada no es válida.',

            'unidad_medida.required' => 'La unidad de medida es obligatoria.',
            'unidad_medida.max' => 'La unidad de medida no debe superar los 30 caracteres.',

            'precio_unitario.required' => 'El precio unitario es obligatorio.',
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
