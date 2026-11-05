<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comunidad', 150);
            $table->string('municipio', 100);
            $table->string('provincia', 100)->nullable();
            $table->string('departamento', 100)->default('La Paz');
            $table->decimal('latitud', 10, 7)->nullable();   // Coordenadas del centro de la comunidad
            $table->decimal('longitud', 10, 7)->nullable();
            $table->integer('altitud_msnm')->nullable();     // Altura sobre el nivel del mar
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunidades');
    }
};
