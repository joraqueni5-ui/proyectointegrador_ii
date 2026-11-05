<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspecciones', function (Blueprint $table) {
            $table->id();

            // Técnico que realizó la inspección
            $table->foreignId('id_usuario')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Parcela inspeccionada
            $table->foreignId('id_parcela')
                ->constrained('parcelas')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Cultivo específico inspeccionado (opcional, puede ser sin cultivo activo)
            $table->foreignId('id_cultivo')
                ->nullable()
                ->constrained('cultivo_variedades')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->date('fecha_visita');
            $table->time('hora_visita')->nullable();

            // Etapa fenológica al momento de la inspección
            $table->foreignId('id_etapa_fenologica')
                ->nullable()
                ->constrained('etapas_fenologicas')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Condiciones ambientales durante la inspección
            $table->enum('condicion_climatica', [
                'Soleado',
                'Nublado',
                'Lluvioso',
                'Helado',
                'Ventoso',
                'Mixto'
            ])->nullable();
            $table->decimal('temperatura_c', 4, 1)->nullable(); // Temperatura en grados Celsius
            $table->integer('humedad_relativa_pct')->nullable(); // 0-100%

            // Porcentaje estimado de plantas afectadas en campo (visual del técnico)
            $table->decimal('pct_plantas_afectadas', 5, 2)->nullable();

            $table->text('observaciones')->nullable();

            $table->enum('estado', [
                'Pendiente',   // Inspección registrada, sin diagnósticos aún
                'En_proceso',  // Se están subiendo fotos
                'Completada',  // Diagnósticos generados
                'Validada',    // Supervisor validó los diagnósticos
            ])->default('Pendiente');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspecciones');
    }
};
