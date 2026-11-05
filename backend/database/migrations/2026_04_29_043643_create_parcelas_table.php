<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_productor')
                ->constrained('productores')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->string('codigo_parcela', 30)->unique();   // Alfanumérico: "P-SUR-01"
            $table->string('nombre_referencial', 150)->nullable(); // "Parcela del cerro"

            // Ubicación GPS
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('coordenadas_texto', 100)->nullable(); // "16°30'S 68°10'O"
            $table->integer('altitud_msnm')->nullable();

            // Características físicas
            $table->decimal('superficie_ha', 8, 2)->nullable();    // En hectáreas
            $table->enum('tipo_suelo', [
                'Arcilloso',
                'Limoso',
                'Arenoso',
                'Franco',
                'Franco-arcilloso',
                'Otro'
            ])->nullable();
            $table->enum('pendiente', ['Plano', 'Suave', 'Moderado', 'Fuerte'])->nullable();
            $table->enum('sistema_riego', ['Secano', 'Riego', 'Mixto'])->default('Secano');

            $table->text('observaciones')->nullable();
            $table->enum('estado', ['Activa', 'Inactiva', 'En_descanso'])->default('Activa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcelas');
    }
};
