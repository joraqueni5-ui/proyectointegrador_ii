<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_producto')
                ->constrained('productos')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreignId('id_almacen')
                ->constrained('almacenes')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Opcional: si el movimiento fue por una venta
            $table->foreignId('id_factura')
                ->nullable()
                ->constrained('facturas')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Usuario que realizó el movimiento
            $table->foreignId('id_usuario')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->enum('tipo', ['Entrada', 'Salida']);
            $table->decimal('cantidad', 10, 3);

            // Stock antes y después del movimiento (auditoría)
            $table->decimal('stock_anterior', 10, 3)->nullable();
            $table->decimal('stock_posterior', 10, 3)->nullable();

            $table->enum('motivo', [
                'Compra',           // Entrada por compra a proveedor
                'Venta',            // Salida por venta a productor
                'Devolucion',       // Entrada por devolución de cliente
                'Ajuste_entrada',   // Corrección de inventario
                'Ajuste_salida',    // Merma, daño, vencimiento
                'Traslado_entrada', // Llegó de otro almacén
                'Traslado_salida',  // Fue a otro almacén
                'Otro',
            ]);

            $table->text('observaciones')->nullable();
            $table->date('fecha_movimiento');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
