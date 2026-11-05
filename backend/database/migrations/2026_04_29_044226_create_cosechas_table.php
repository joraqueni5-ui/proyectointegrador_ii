<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cosechas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_parcela')
                ->constrained('parcelas')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreignId('id_cultivo')
                ->nullable()
                ->constrained('cultivo_variedades')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Técnico que registró la cosecha
            $table->foreignId('id_usuario')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->date('fecha_cosecha');

            // Producción
            $table->decimal('cantidad_toneladas', 8, 3);       // Toneladas métricas
            $table->decimal('rendimiento_t_ha', 6, 3)->nullable(); // Calculado: toneladas/hectárea

            // Clasificación de la cosecha
            $table->enum('calidad', ['Primera', 'Segunda', 'Tercera', 'Descarte'])->default('Primera');
            $table->enum('destino', ['Venta', 'Semilla', 'Autoconsumo', 'Mixto'])->default('Venta');

            // Precio referencial al momento de cosecha
            $table->decimal('precio_unitario_bs', 8, 2)->nullable(); // Bs por quintal/kg
            $table->decimal('ingreso_estimado_bs', 10, 2)->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosechas');
    }
};
