<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'nro_cotizacion',
        'id_productor',
        'id_usuario',
        'id_aprobador',
        'convertida_factura_id',
        'fecha_emision',
        'fecha_vencimiento',
        'subtotal',
        'descuento',
        'monto_total',
        'metodo_pago',
        'estado',
        'observaciones',
        'motivo_rechazo',
        'aprobada_at',
        'rechazada_at',
        'convertida_at',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'aprobada_at' => 'datetime',
        'rechazada_at' => 'datetime',
        'convertida_at' => 'datetime',
    ];

    public function productor()
    {
        return $this->belongsTo(Productor::class, 'id_productor');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'id_aprobador');
    }

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'convertida_factura_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCotizacion::class, 'id_cotizacion');
    }
}
