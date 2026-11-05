<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();

            // Productor que compra (cliente)
            $table->foreignId('id_productor')
                ->constrained('productores')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Usuario que registró la venta
            $table->foreignId('id_usuario')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->string('nro_factura', 30)->unique();       // Alfanumérico: "PRNP-2026-001"
            $table->enum('tipo', ['Venta', 'Devolucion'])->default('Venta');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();      // Para créditos

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('monto_total', 12, 2);

            $table->enum('metodo_pago', [
                'Efectivo',
                'Transferencia',
                'QR',
                'Credito',
                'Otro'
            ])->default('Efectivo');

            $table->enum('estado', [
                'Pendiente',  // Crédito sin pagar
                'Pagada',
                'Anulada',
                'Parcial',    // Pago parcial
            ])->default('Pagada');

            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
