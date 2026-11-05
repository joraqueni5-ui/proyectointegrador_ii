<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_almacen', 150);             // "Sede Viacha", "Depósito La Paz"
            $table->string('ubicacion', 255)->nullable();
            $table->string('departamento', 100)->default('La Paz');

            // Responsable del almacén
            $table->foreignId('id_responsable')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->decimal('capacidad_maxima_m3', 8, 2)->nullable(); // Metros cúbicos
            $table->string('telefono_contacto', 20)->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['Activo', 'Inactivo', 'En_mantenimiento'])->default('Activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacenes');
    }
};
