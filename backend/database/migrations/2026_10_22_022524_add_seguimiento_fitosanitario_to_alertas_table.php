<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | RESPONSABLE DEL SEGUIMIENTO
            |--------------------------------------------------------------------------
            |
            | No es necesariamente el mismo usuario que recibió la alerta.
            | Representa al técnico/agronómo responsable de darle seguimiento.
            |
            */
            $table->foreignId('id_responsable')
                ->nullable()
                ->after('id_usuario_destino')
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CICLO DE VIDA FITOSANITARIO
            |--------------------------------------------------------------------------
            |
            | Valores esperados:
            |
            | Nueva
            | En_revision
            | Planificada
            | En_tratamiento
            | En_seguimiento
            | Resuelta
            | Escalada
            | Reincidente
            | Descartada
            |
            */
            $table->string('estado_seguimiento', 30)
                ->default('Nueva')
                ->after('estado');

            /*
            |--------------------------------------------------------------------------
            | PLAN DE MANEJO
            |--------------------------------------------------------------------------
            */
            $table->text('plan_manejo')
                ->nullable()
                ->after('estado_seguimiento');

            /*
            |--------------------------------------------------------------------------
            | FECHAS DE CONTROL
            |--------------------------------------------------------------------------
            */
            $table->timestamp('fecha_inicio_tratamiento')
                ->nullable()
                ->after('plan_manejo');

            $table->timestamp('proxima_revision')
                ->nullable()
                ->after('fecha_inicio_tratamiento');

            $table->timestamp('ultima_revision_at')
                ->nullable()
                ->after('proxima_revision');

            /*
            |--------------------------------------------------------------------------
            | PROGRESO
            |--------------------------------------------------------------------------
            |
            | Rango esperado: 0 - 100
            |
            */
            $table->unsignedTinyInteger('progreso_seguimiento')
                ->default(0)
                ->after('ultima_revision_at');

            /*
            |--------------------------------------------------------------------------
            | RESULTADO DEL SEGUIMIENTO
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | Mejorando
            | Sin_cambios
            | Empeorando
            | Controlado
            |
            */
            $table->string('resultado_seguimiento', 30)
                ->nullable()
                ->after('progreso_seguimiento');

            $table->text('observacion_cierre')
                ->nullable()
                ->after('resultado_seguimiento');

            $table->timestamp('fecha_resolucion')
                ->nullable()
                ->after('observacion_cierre');

            /*
            |--------------------------------------------------------------------------
            | ÍNDICES
            |--------------------------------------------------------------------------
            */
            $table->index('estado_seguimiento');
            $table->index('proxima_revision');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {

            $table->dropIndex([
                'estado_seguimiento'
            ]);

            $table->dropIndex([
                'proxima_revision'
            ]);

            $table->dropConstrainedForeignId(
                'id_responsable'
            );

            $table->dropColumn([
                'estado_seguimiento',
                'plan_manejo',
                'fecha_inicio_tratamiento',
                'proxima_revision',
                'ultima_revision_at',
                'progreso_seguimiento',
                'resultado_seguimiento',
                'observacion_cierre',
                'fecha_resolucion',
            ]);
        });
    }
};
