<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_diagnostico')
                ->constrained('diagnosticos_cnn')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Quién recibe la alerta (técnico responsable de la parcela)
            $table->foreignId('id_usuario_destino')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->enum('nivel_riesgo', ['Bajo', 'Medio', 'Alto', 'Critico']);
            $table->string('titulo', 200);
            $table->text('mensaje');

            // Acción recomendada urgente
            $table->text('accion_recomendada')->nullable();

            $table->date('fecha_emision');
            $table->date('fecha_limite_accion')->nullable(); // Plazo para actuar

            $table->boolean('leida')->default(false);
            $table->timestamp('fecha_lectura')->nullable();

            $table->enum('estado', ['Activa', 'Atendida', 'Ignorada', 'Vencida'])->default('Activa');
            $table->text('accion_tomada')->nullable(); // Qué hizo el técnico

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
