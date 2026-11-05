<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlmacenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_almacen' => ['sometimes', 'required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'departamento' => ['sometimes', 'required', 'string', 'max:100'],
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
            'nombre_almacen.max' => 'El nombre del almacén no debe superar los 150 caracteres.',

            'ubicacion.max' => 'La ubicación no debe superar los 255 caracteres.',

            'departamento.required' => 'El departamento es obligatorio.',
            'departamento.max' => 'El departamento no debe superar los 100 caracteres.',

            'id_responsable.exists' => 'El responsable seleccionado no existe.',

            'capacidad_maxima_m3.numeric' => 'La capacidad máxima debe ser numérica.',
            'capacidad_maxima_m3.min' => 'La capacidad máxima no puede ser negativa.',

            'telefono_contacto.max' => 'El teléfono de contacto no debe superar los 30 caracteres.',

            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
