<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosticos_cnn', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_inspeccion')
                ->constrained('inspecciones')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Enfermedad detectada por la CNN
            $table->foreignId('id_enfermedad')
                ->nullable()
                ->constrained('enfermedades')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Foto tomada en campo
            $table->string('ruta_fotografia', 500);            // Path del archivo almacenado
            $table->string('nombre_archivo', 255)->nullable();

            // Resultado de la CNN
            $table->decimal('porcentaje_confianza', 5, 2);     // 0.00 - 100.00
            $table->json('top3_predicciones')->nullable();      // [{"enfermedad":"TT","confianza":95.2}, ...]
            $table->string('modelo_version', 50)->nullable();   // "CNN-PROINPA-v1.2"

            // Severidad estimada de la infección
            $table->enum('nivel_severidad', [
                'Sin_afeccion',  // 0%
                'Leve',          // 1-25%
                'Moderada',      // 26-50%
                'Severa',        // 51-75%
                'Muy_severa',    // 76-100%
            ])->nullable();

            // Recomendación automática generada por el sistema
            $table->text('recomendacion')->nullable();

            // Validación por supervisor/administrador
            $table->enum('estado_validacion', [
                'Pendiente',    // Sin revisar
                'Confirmado',   // El supervisor confirmó el diagnóstico
                'Corregido',    // El supervisor lo corrigió
                'Rechazado',    // Foto inválida o diagnóstico incorrecto
            ])->default('Pendiente');

            $table->foreignId('validado_por')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->text('observaciones_validacion')->nullable();
            $table->timestamp('fecha_validacion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosticos_cnn');
    }
};
