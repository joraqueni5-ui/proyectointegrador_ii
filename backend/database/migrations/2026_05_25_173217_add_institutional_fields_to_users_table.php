<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ci', 20)->nullable()->unique()->after('materno');
            $table->string('ci_extension', 5)->nullable()->after('ci');
            $table->string('sexo', 30)->nullable()->after('ci_extension');
            $table->string('cargo', 100)->nullable()->after('celular');
            $table->string('area', 100)->nullable()->after('cargo');
            $table->string('oficina_regional', 100)->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ci']);
            $table->dropColumn([
                'ci',
                'ci_extension',
                'sexo',
                'cargo',
                'area',
                'oficina_regional',
            ]);
        });
    }
};
