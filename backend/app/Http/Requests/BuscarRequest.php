<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuscarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->input('q', ''))]);
    }

    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:80']];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Escribe al menos 2 caracteres para buscar.',
            'q.min' => 'Escribe al menos 2 caracteres para buscar.',
            'q.max' => 'La búsqueda no puede superar los 80 caracteres.',
        ];
    }
}
