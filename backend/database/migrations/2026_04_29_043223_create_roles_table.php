<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();           // Administrador, Técnico, Supervisor
            $table->string('descripcion')->nullable();
            $table->json('permisos')->nullable();             // ["ver_diagnosticos", "crear_inspecciones"]
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Insertar roles base del sistema PROINPA
        DB::table('roles')->insert([
            [
                'nombre'      => 'Administrador',
                'descripcion' => 'Acceso total al sistema. Gestiona usuarios, reportes y configuración.',
                'permisos'    => json_encode([
                    'gestionar_usuarios',
                    'gestionar_roles',
                    'ver_reportes',
                    'exportar_reportes',
                    'gestionar_productores',
                    'gestionar_parcelas',
                    'ver_diagnosticos',
                    'validar_diagnosticos',
                    'gestionar_inventario',
                    'gestionar_facturas',
                    'gestionar_almacenes',
                    'ver_alertas',
                    'gestionar_alertas',
                ]),
                'activo'      => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'nombre'      => 'Técnico',
                'descripcion' => 'Técnico extensionista. Realiza inspecciones de campo y sube diagnósticos.',
                'permisos'    => json_encode([
                    'gestionar_productores',
                    'gestionar_parcelas',
                    'crear_inspecciones',
                    'ver_inspecciones',
                    'crear_diagnosticos',
                    'ver_diagnosticos',
                    'ver_alertas',
                    'registrar_cosechas',
                ]),
                'activo'      => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'nombre'      => 'Supervisor',
                'descripcion' => 'Supervisa técnicos, valida diagnósticos y ve reportes.',
                'permisos'    => json_encode([
                    'ver_usuarios',
                    'gestionar_productores',
                    'gestionar_parcelas',
                    'ver_inspecciones',
                    'ver_diagnosticos',
                    'validar_diagnosticos',
                    'ver_reportes',
                    'ver_alertas',
                    'gestionar_alertas',
                ]),
                'activo'      => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
