<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_producto', 30)->unique();   // "SEM-001", "BIO-023"
            $table->string('nombre_producto', 200);
            $table->enum('categoria', [
                'Semilla',      // Tubérculos-semilla certificados
                'Bioinsumo',    // Productos biológicos (bioestimulantes, biofungicidas)
                'Agroquimico',  // Fungicidas, bactericidas, nematicidas
                'Fertilizante', // Abonos, nutrientes
                'Equipo',       // Herramientas, equipos de campo
                'Otro',
            ]);
            $table->text('descripcion')->nullable();
            $table->string('unidad_medida', 30);               // "kg", "litro", "unidad", "quintal"
            $table->decimal('precio_unitario', 10, 2);         // Precio de venta en Bs
            $table->decimal('precio_costo', 10, 2)->nullable(); // Precio de costo
            $table->integer('stock_minimo')->default(0);       // Alerta cuando llegue a este nivel
            $table->string('imagen_url', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
