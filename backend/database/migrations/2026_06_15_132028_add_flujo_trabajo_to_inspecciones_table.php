<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspecciones', function (Blueprint $table) {
            $table->string('tipo_inspeccion', 30)
                ->default('Cultivo')
                ->after('id_etapa_fenologica');

            $table->foreignId('id_programado_por')
                ->nullable()
                ->after('id_usuario')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreignId('id_validado_por')
                ->nullable()
                ->after('id_programado_por')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->timestamp('fecha_inicio')->nullable()->after('hora_visita');
            $table->timestamp('fecha_finalizacion')->nullable()->after('fecha_inicio');
            $table->timestamp('fecha_validacion')->nullable()->after('fecha_finalizacion');

            $table->text('motivo_no_realizada')->nullable()->after('observaciones');
            $table->date('fecha_reprogramada')->nullable()->after('motivo_no_realizada');
            $table->text('recomendacion_reprogramacion')->nullable()->after('fecha_reprogramada');

            $table->string('condicion_terreno', 80)->nullable()->after('recomendacion_reprogramacion');
            $table->text('recomendacion_pre_siembra')->nullable()->after('condicion_terreno');
        });

        // PostgreSQL: quitar restricción CHECK antigua del campo estado y agregar la nueva
        DB::statement("
            DO $$
            DECLARE
                constraint_name text;
            BEGIN
                SELECT conname INTO constraint_name
                FROM pg_constraint
                WHERE conrelid = 'inspecciones'::regclass
                AND contype = 'c'
                AND pg_get_constraintdef(oid) LIKE '%estado%';

                IF constraint_name IS NOT NULL THEN
                    EXECUTE 'ALTER TABLE inspecciones DROP CONSTRAINT ' || constraint_name;
                END IF;
            END $$;
        ");

        DB::statement("
            ALTER TABLE inspecciones
            ADD CONSTRAINT inspecciones_estado_check
            CHECK (estado IN ('Pendiente', 'En_proceso', 'Completada', 'Validada', 'No_realizada'))
        ");

        DB::statement("
            ALTER TABLE inspecciones
            ADD CONSTRAINT inspecciones_tipo_inspeccion_check
            CHECK (tipo_inspeccion IN ('Cultivo', 'Pre_siembra'))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inspecciones DROP CONSTRAINT IF EXISTS inspecciones_tipo_inspeccion_check");
        DB::statement("ALTER TABLE inspecciones DROP CONSTRAINT IF EXISTS inspecciones_estado_check");

        DB::statement("
            ALTER TABLE inspecciones
            ADD CONSTRAINT inspecciones_estado_check
            CHECK (estado IN ('Pendiente', 'En_proceso', 'Completada', 'Validada'))
        ");

        Schema::table('inspecciones', function (Blueprint $table) {
            $table->dropForeign(['id_programado_por']);
            $table->dropForeign(['id_validado_por']);

            $table->dropColumn([
                'tipo_inspeccion',
                'id_programado_por',
                'id_validado_por',
                'fecha_inicio',
                'fecha_finalizacion',
                'fecha_validacion',
                'motivo_no_realizada',
                'fecha_reprogramada',
                'recomendacion_reprogramacion',
                'condicion_terreno',
                'recomendacion_pre_siembra',
            ]);
        });
    }
};
