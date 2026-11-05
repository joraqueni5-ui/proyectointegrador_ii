<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_producto')
                ->constrained('productos')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreignId('id_almacen')
                ->constrained('almacenes')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->decimal('stock_actual', 10, 3)->default(0); // Cantidad disponible
            $table->decimal('stock_minimo', 10, 3)->default(0); // Nivel mínimo de alerta
            $table->decimal('stock_maximo', 10, 3)->nullable();  // Capacidad máxima
            $table->timestamp('ultima_actualizacion')->nullable();

            // Una combinación producto-almacén debe ser única
            $table->unique(['id_producto', 'id_almacen']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
