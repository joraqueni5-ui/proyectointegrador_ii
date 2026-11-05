<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enfermedad_producto_recomendados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_enfermedad')
                ->constrained('enfermedades')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('id_producto')
                ->constrained('productos')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->enum('tipo_recomendacion', [
                'Preventivo',
                'Curativo',
                'Control',
                'Apoyo',
                'Otro',
            ])->default('Control');

            $table->string('dosis_recomendada', 150)->nullable();

            $table->enum('prioridad', [
                'Alta',
                'Media',
                'Baja',
            ])->default('Media');

            $table->text('observaciones')->nullable();

            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(['id_enfermedad', 'id_producto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enfermedad_producto_recomendados');
    }
};
