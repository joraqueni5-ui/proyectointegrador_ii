<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            // Puntaje calculado por el motor de prioridad.
            // Rango esperado: 0 a 100.
            $table->unsignedSmallInteger('puntaje_prioridad')
                ->default(0);

            // Guarda los factores que explican el puntaje.
            // Ejemplo:
            // [
            //   {"factor":"Riesgo de enfermedad","puntos":30},
            //   {"factor":"Severidad","puntos":25}
            // ]
            $table->json('motivos_prioridad')
                ->nullable();

            // Indica si la prioridad fue calculada automáticamente.
            $table->boolean('prioridad_automatica')
                ->default(false);

            // Fecha de la última vez que se recalculó.
            $table->timestamp('prioridad_calculada_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn([
                'puntaje_prioridad',
                'motivos_prioridad',
                'prioridad_automatica',
                'prioridad_calculada_at',
            ]);
        });
    }
};
