<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda lo que la CNN calcula además de la clase: métricas de calidad de la foto
 * (nitidez, iluminación, contraste, vegetación), margen entre las dos primeras clases
 * y datos del modelo (versión, calibración). Solo agrega una columna nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosticos_cnn', function (Blueprint $table) {
            $table->json('detalle_inferencia')->nullable()->after('top3_predicciones');
        });
    }

    public function down(): void
    {
        Schema::table('diagnosticos_cnn', function (Blueprint $table) {
            $table->dropColumn('detalle_inferencia');
        });
    }
};
