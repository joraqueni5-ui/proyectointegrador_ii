<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlmacenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_almacen' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'departamento' => ['required', 'string', 'max:100'],
            'id_responsable' => ['nullable', 'exists:users,id'],
            'capacidad_maxima_m3' => ['nullable', 'numeric', 'min:0'],
            'telefono_contacto' => ['nullable', 'string', 'max:30'],
            'observaciones' => ['nullable', 'string'],
            'estado' => ['nullable', 'in:Activo,Inactivo'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_almacen.required' => 'El nombre del almacén es obligatorio.',
            'departamento.required' => 'El departamento es obligatorio.',
            'capacidad_maxima_m3.numeric' => 'La capacidad máxima debe ser numérica.',
            'capacidad_maxima_m3.min' => 'La capacidad máxima no puede ser negativa.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
