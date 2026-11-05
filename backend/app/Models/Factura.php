<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'id_productor',
        'id_usuario',
        'nro_factura',
        'tipo',
        'fecha_emision',
        'fecha_vencimiento',
        'subtotal',
        'descuento',
        'monto_total',
        'metodo_pago',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'monto_total' => 'decimal:2',
    ];

    public function productor()
    {
        return $this->belongsTo(Productor::class, 'id_productor');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleFactura::class, 'id_factura');
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'id_factura');
    }
}
