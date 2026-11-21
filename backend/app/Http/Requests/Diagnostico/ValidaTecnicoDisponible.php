<?php

namespace App\Http\Requests\Diagnostico;

use App\Models\Inspeccion;
use Illuminate\Validation\Validator;

/**
 * Un técnico no puede tener dos inspecciones en la misma fecha y hora.
 */
trait ValidaTecnicoDisponible
{
    protected function validarTecnicoDisponible(Validator $validator, ?Inspeccion $actual = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $idUsuario = $this->input('id_usuario', $actual?->id_usuario);
        $fecha = $this->input('fecha_visita', $actual?->fecha_visita?->toDateString());
        $hora = $this->input('hora_visita', $actual?->hora_visita);

        if (! $idUsuario || ! $fecha || ! $hora) {
            return;
        }

        $hora = substr((string) $hora, 0, 5);

        $choque = Inspeccion::where('id_usuario', $idUsuario)
            ->whereDate('fecha_visita', $fecha)
            ->whereRaw("to_char(hora_visita, 'HH24:MI') = ?", [$hora])
            ->whereNotIn('estado', ['No_realizada'])
            ->when($actual, fn ($q) => $q->where('id', '!=', $actual->id))
            ->exists();

        if ($choque) {
            $validator->errors()->add('hora_visita', "El técnico ya tiene otra inspección el {$fecha} a las {$hora}. Elige otra hora u otro técnico.");
        }
    }
}
