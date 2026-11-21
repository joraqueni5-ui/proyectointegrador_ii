<?php

namespace App\Http\Requests\Comercial;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Productor;
use Illuminate\Validation\Validator;

/**
 * Validaciones compartidas por ventas y cotizaciones:
 * productor, productos y almacenes activos, y descuentos coherentes.
 */
trait ValidaDetallesComerciales
{
    protected function reglasDetalles(): array
    {
        return [
            'id_productor' => ['required', 'exists:productores,id'],
            'metodo_pago' => ['required', 'in:Efectivo,Transferencia,QR,Credito,Otro'],
            'fecha_emision' => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'observaciones' => ['nullable', 'string', 'max:1000'],

            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.id_producto' => ['required', 'exists:productos,id'],
            'detalles.*.id_almacen' => ['required', 'exists:almacenes,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'max:1000000', 'decimal:0,3'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0.01', 'max:9999999', 'decimal:0,2'],
            'detalles.*.descuento_linea' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    protected function mensajesDetalles(): array
    {
        return [
            'id_productor.required' => 'Debe seleccionar un productor.',
            'id_productor.exists' => 'El productor seleccionado no existe.',

            'metodo_pago.required' => 'Debe seleccionar el método de pago.',
            'metodo_pago.in' => 'El método de pago no es válido.',

            'fecha_emision.date' => 'La fecha de emisión no tiene un formato válido.',
            'fecha_emision.before_or_equal' => 'La fecha de emisión no puede ser futura.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento no tiene un formato válido.',
            'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior a la fecha de emisión.',

            'descuento.numeric' => 'El descuento debe ser numérico.',
            'descuento.min' => 'El descuento no puede ser negativo.',
            'descuento.decimal' => 'El descuento admite como máximo 2 decimales.',

            'observaciones.max' => 'Las observaciones no deben superar los 1000 caracteres.',

            'detalles.required' => 'Debe agregar al menos un producto.',
            'detalles.array' => 'Los detalles no tienen un formato válido.',
            'detalles.min' => 'Debe agregar al menos un producto.',
            'detalles.max' => 'No se pueden registrar más de 100 productos en un mismo documento.',

            'detalles.*.id_producto.required' => 'Debe seleccionar un producto.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',

            'detalles.*.id_almacen.required' => 'Debe seleccionar un almacén.',
            'detalles.*.id_almacen.exists' => 'Uno de los almacenes seleccionados no existe.',

            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.cantidad.numeric' => 'La cantidad debe ser numérica.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.cantidad.max' => 'La cantidad no puede superar 1.000.000 unidades.',
            'detalles.*.cantidad.decimal' => 'La cantidad admite como máximo 3 decimales.',

            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
            'detalles.*.precio_unitario.numeric' => 'El precio unitario debe ser numérico.',
            'detalles.*.precio_unitario.min' => 'El precio unitario debe ser mayor a 0.',
            'detalles.*.precio_unitario.max' => 'El precio unitario no puede superar 9.999.999 Bs.',
            'detalles.*.precio_unitario.decimal' => 'El precio unitario admite como máximo 2 decimales.',

            'detalles.*.descuento_linea.numeric' => 'El descuento de línea debe ser numérico.',
            'detalles.*.descuento_linea.min' => 'El descuento de línea no puede ser negativo.',
            'detalles.*.descuento_linea.decimal' => 'El descuento de línea admite como máximo 2 decimales.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! Productor::where('id', $this->input('id_productor'))->where('activo', true)->exists()) {
                $validator->errors()->add('id_productor', 'El productor seleccionado está inactivo.');
            }

            $detalles = $this->input('detalles', []);

            $productosInactivos = Producto::whereIn('id', array_column($detalles, 'id_producto'))
                ->where('activo', false)
                ->pluck('id')
                ->all();

            $almacenesInactivos = Almacen::whereIn('id', array_column($detalles, 'id_almacen'))
                ->where('estado', '!=', 'Activo')
                ->pluck('id')
                ->all();

            $subtotal = 0;

            foreach ($detalles as $i => $detalle) {
                $importe = (float) $detalle['cantidad'] * (float) $detalle['precio_unitario'];
                $descuentoLinea = (float) ($detalle['descuento_linea'] ?? 0);

                if (in_array($detalle['id_producto'], $productosInactivos)) {
                    $validator->errors()->add("detalles.$i.id_producto", 'Uno de los productos seleccionados está inactivo.');
                }

                if (in_array($detalle['id_almacen'], $almacenesInactivos)) {
                    $validator->errors()->add("detalles.$i.id_almacen", 'Uno de los almacenes seleccionados está inactivo.');
                }

                if ($descuentoLinea > $importe) {
                    $validator->errors()->add("detalles.$i.descuento_linea", 'El descuento de línea no puede superar el importe del producto.');
                }

                $subtotal += max($importe - $descuentoLinea, 0);
            }

            if ((float) $this->input('descuento', 0) > $subtotal) {
                $validator->errors()->add('descuento', 'El descuento no puede superar el subtotal.');
            }
        });
    }
}
