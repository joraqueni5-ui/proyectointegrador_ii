<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna id_rol sin FK primero
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_rol')
                ->default(2)
                ->after('email');
        });

        // 2. Migrar datos existentes de 'role' a 'id_rol'
        DB::statement("
            UPDATE users SET id_rol =
                CASE role
                    WHEN 'admin' THEN 1
                    ELSE 2
                END
        ");

        // 3. Agregar FK ahora que los datos están migrados
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('id_rol')
                ->references('id')
                ->on('roles')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });

        // 4. Agregar campo activo si no existe
        if (!Schema::hasColumn('users', 'activo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('id_rol');
            });
        }

        // 5. Eliminar columna 'role' antigua
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        // Revertir: quitar FK y restaurar campo role
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_rol']);
            $table->dropColumn('id_rol');
            $table->string('role')->default('user');
        });

        // Restaurar datos aproximados
        DB::statement("UPDATE users SET role = 'user'");
    }
};
