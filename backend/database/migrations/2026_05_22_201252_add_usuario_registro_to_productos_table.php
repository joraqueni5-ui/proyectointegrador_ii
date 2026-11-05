<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('id_usuario_registro')
                ->nullable()
                ->after('activo')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('id_usuario_actualizacion')
                ->nullable()
                ->after('id_usuario_registro')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_usuario_actualizacion');
            $table->dropConstrainedForeignId('id_usuario_registro');
        });
    }
};
