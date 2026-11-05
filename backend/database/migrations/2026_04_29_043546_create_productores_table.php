<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_comunidad')
                ->constrained('comunidades')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->string('nombre_completo', 200);
            $table->string('carnet_identidad', 20)->unique();    // Alfanumérico (puede tener letras)
            $table->string('telefono', 20)->nullable();           // Alfanumérico (acepta +591)
            $table->enum('sexo', ['M', 'F', 'Otro'])->nullable();
            $table->date('fecha_nacimiento')->nullable();

            // Dirección adicional
            $table->string('direccion', 255)->nullable();

            // Superficie total de producción (referencial)
            $table->decimal('superficie_total_ha', 8, 2)->nullable();

            // Observaciones del técnico sobre el productor
            $table->text('observaciones')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productores');
    }
};
