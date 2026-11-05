<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cultivo_variedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_parcela')
                ->constrained('parcelas')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Variedad de papa cultivada
            $table->string('variedad_papa', 100);             // "Waych'a", "Imilla Negra", "Désirée"
            $table->date('fecha_siembra');

            // Cantidad sembrada
            $table->decimal('cantidad_semilla_kg', 8, 2)->nullable();
            $table->string('procedencia_semilla', 150)->nullable(); // "Certificada PROINPA", "Propia"

            // Estado actual del cultivo
            $table->foreignId('id_etapa_fenologica')
                ->nullable()
                ->constrained('etapas_fenologicas')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->date('fecha_estimada_cosecha')->nullable();
            $table->text('observaciones')->nullable();

            $table->enum('estado', ['Activo', 'Cosechado', 'Perdido', 'Abandonado'])->default('Activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultivo_variedades');
    }
};
