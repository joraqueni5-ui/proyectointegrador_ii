<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear o actualizar roles y permisos
        $this->call([
            RolesSeeder::class,
        ]);

        // 2. Crear usuario administrador principal
        $adminRoleId = Rol::where('nombre', 'Administrador')->value('id');

        if ($adminRoleId) {
            User::updateOrCreate(
                ['email' => 'admin@proinpa.test'],
                [
                    'name' => 'Administrador',
                    'paterno' => 'PROINPA',
                    'materno' => 'Sistema',
                    'celular' => '70000000',
                    'id_rol' => $adminRoleId,
                    'activo' => true,
                    'password' => Hash::make('Admin123*'),
                ]
            );
        }

        // 3. Crear usuarios demo para probar roles
        $this->call([
            DemoUsersSeeder::class,
        ]);

        // 4. Cargar datos complementarios
        $this->call([
            EnfermedadProductoRecomendadoSeeder::class,
        ]);
    }
}
