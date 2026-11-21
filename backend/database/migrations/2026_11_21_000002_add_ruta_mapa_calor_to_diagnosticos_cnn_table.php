<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruta (en el disco "public") del mapa de calor Grad-CAM superpuesto a la foto.
 * Es opcional: los diagnósticos anteriores y los que no pudieron generarlo quedan en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosticos_cnn', function (Blueprint $table) {
            $table->string('ruta_mapa_calor', 500)->nullable()->after('ruta_fotografia');
        });
    }

    public function down(): void
    {
        Schema::table('diagnosticos_cnn', function (Blueprint $table) {
            $table->dropColumn('ruta_mapa_calor');
        });
    }
};
