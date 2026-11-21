<?php

namespace App\Http\Requests\Agricola;

use App\Models\Parcela;
use App\Models\Productor;
use Illuminate\Validation\Validator;

/**
 * La suma de las superficies de las parcelas activas de un productor no puede superar
 * su superficie total declarada (si la declaró).
 */
trait ValidaSuperficieParcela
{
    protected function validarSuperficieContraTotal(Validator $validator, ?Parcela $actual = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $idProductor = $this->input('id_productor', $actual?->id_productor);
        $superficie = $this->input('superficie_ha', $actual?->superficie_ha);
        $estado = $this->input('estado', $actual?->estado ?? 'Activa');

        if (! $idProductor || $superficie === null || $superficie === '' || $estado === 'Inactiva') {
            return;
        }

        $total = (float) Productor::whereKey($idProductor)->value('superficie_total_ha');

        if ($total <= 0) {
            return;
        }

        $otras = (float) Parcela::where('id_productor', $idProductor)
            ->where('estado', '!=', 'Inactiva')
            ->when($actual, fn ($q) => $q->where('id', '!=', $actual->id))
            ->sum('superficie_ha');

        if ($otras + (float) $superficie > $total + 0.0001) {
            $disponible = max($total - $otras, 0);
            $validator->errors()->add(
                'superficie_ha',
                'La superficie supera lo disponible del productor: su total declarado es '
                .number_format($total, 2, ',', '.').' ha y le quedan '.number_format($disponible, 2, ',', '.').' ha.'
            );
        }
    }
}
