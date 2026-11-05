<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_cotizaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_cotizacion')
                ->constrained('cotizaciones')
                ->cascadeOnDelete();

            $table->foreignId('id_producto')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->foreignId('id_almacen')
                ->constrained('almacenes')
                ->restrictOnDelete();

            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento_linea', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);

            $table->timestamps();

            $table->index('id_cotizacion');
            $table->index('id_producto');
            $table->index('id_almacen');
        });

        DB::statement("
            ALTER TABLE detalle_cotizaciones
            ADD CONSTRAINT detalle_cotizaciones_cantidad_check
            CHECK (cantidad > 0)
        ");

        DB::statement("
            ALTER TABLE detalle_cotizaciones
            ADD CONSTRAINT detalle_cotizaciones_precio_check
            CHECK (precio_unitario >= 0)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_cotizaciones');
    }
};
