<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo reutilizable de etapas fenológicas de la papa
        Schema::create('etapas_fenologicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();          // "Emergencia", "Tuberización"
            $table->string('codigo', 20)->unique();           // "BBCH-09", "BBCH-40"
            $table->integer('dias_desde_siembra_min')->nullable();
            $table->integer('dias_desde_siembra_max')->nullable();
            $table->text('descripcion')->nullable();
            $table->integer('orden')->default(0);             // Para ordenar cronológicamente
            $table->timestamps();
        });

        // Insertar etapas fenológicas estándar de la papa
        DB::table('etapas_fenologicas')->insert([
            ['nombre' => 'Siembra',           'codigo' => 'BBCH-00', 'dias_desde_siembra_min' => 0,   'dias_desde_siembra_max' => 0,   'orden' => 1, 'descripcion' => 'Día de siembra del tubérculo-semilla.', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Emergencia',        'codigo' => 'BBCH-09', 'dias_desde_siembra_min' => 15,  'dias_desde_siembra_max' => 30,  'orden' => 2, 'descripcion' => 'Brote emerge del suelo (≥50% plantas).', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Desarrollo foliar', 'codigo' => 'BBCH-19', 'dias_desde_siembra_min' => 30,  'dias_desde_siembra_max' => 50,  'orden' => 3, 'descripcion' => 'Desarrollo de hojas y tallos principales.', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Tuberización',      'codigo' => 'BBCH-40', 'dias_desde_siembra_min' => 40,  'dias_desde_siembra_max' => 65,  'orden' => 4, 'descripcion' => 'Inicio de formación de tubérculos.', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Llenado tubérculo', 'codigo' => 'BBCH-70', 'dias_desde_siembra_min' => 60,  'dias_desde_siembra_max' => 90,  'orden' => 5, 'descripcion' => 'Engrosamiento activo de tubérculos.', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Maduración',        'codigo' => 'BBCH-87', 'dias_desde_siembra_min' => 90,  'dias_desde_siembra_max' => 120, 'orden' => 6, 'descripcion' => 'Senescencia del follaje, tubérculos maduros.', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Cosecha',           'codigo' => 'BBCH-99', 'dias_desde_siembra_min' => 100, 'dias_desde_siembra_max' => 150, 'orden' => 7, 'descripcion' => 'Extracción de tubérculos del suelo.', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('etapas_fenologicas');
    }
};
