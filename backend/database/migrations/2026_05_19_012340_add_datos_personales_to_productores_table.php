<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productores', function (Blueprint $table) {
            if (!Schema::hasColumn('productores', 'nombres')) {
                $table->string('nombres', 100)->nullable()->after('id_comunidad');
            }

            if (!Schema::hasColumn('productores', 'apellido_paterno')) {
                $table->string('apellido_paterno', 100)->nullable()->after('nombres');
            }

            if (!Schema::hasColumn('productores', 'apellido_materno')) {
                $table->string('apellido_materno', 100)->nullable()->after('apellido_paterno');
            }

            if (!Schema::hasColumn('productores', 'complemento_ci')) {
                $table->string('complemento_ci', 10)->nullable()->after('carnet_identidad');
            }
        });

        DB::table('productores')
            ->whereNull('nombres')
            ->update([
                'nombres' => DB::raw('nombre_completo'),
                'apellido_paterno' => 'Sin especificar',
            ]);
    }

    public function down(): void
    {
        Schema::table('productores', function (Blueprint $table) {
            if (Schema::hasColumn('productores', 'complemento_ci')) {
                $table->dropColumn('complemento_ci');
            }

            if (Schema::hasColumn('productores', 'apellido_materno')) {
                $table->dropColumn('apellido_materno');
            }

            if (Schema::hasColumn('productores', 'apellido_paterno')) {
                $table->dropColumn('apellido_paterno');
            }

            if (Schema::hasColumn('productores', 'nombres')) {
                $table->dropColumn('nombres');
            }
        });
    }
};
