<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_facturas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_factura')
                ->constrained('facturas')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreignId('id_producto')
                ->constrained('productos')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->decimal('cantidad', 10, 3);

            // Precio al MOMENTO de la venta (no el precio actual del producto)
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('descuento_linea', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2);                // cantidad * precio - descuento

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_facturas');
    }
};
