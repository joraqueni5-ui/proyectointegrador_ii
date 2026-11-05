<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'Proinpa123*';

        $usuarios = [
            [
                'rol' => 'Superadmin',
                'name' => 'Superadmin',
                'paterno' => 'Sistema',
                'materno' => 'PROINPA',
                'email' => 'superadmin@proinpa.test',
                'celular' => '70000001',
            ],
            [
                'rol' => 'Administrador',
                'name' => 'Administrador',
                'paterno' => 'General',
                'materno' => 'PROINPA',
                'email' => 'admin@proinpa.test',
                'celular' => '70000002',
            ],
            [
                'rol' => 'Agrónomo',
                'name' => 'Agrónomo',
                'paterno' => 'Técnico',
                'materno' => 'PROINPA',
                'email' => 'agronomo@proinpa.test',
                'celular' => '70000003',
            ],
            [
                'rol' => 'Técnico',
                'name' => 'Técnico',
                'paterno' => 'Campo',
                'materno' => 'PROINPA',
                'email' => 'tecnico@proinpa.test',
                'celular' => '70000004',
            ],
            [
                'rol' => 'Encargado de almacén',
                'name' => 'Encargado',
                'paterno' => 'Almacén',
                'materno' => 'PROINPA',
                'email' => 'almacen@proinpa.test',
                'celular' => '70000005',
            ],
            [
                'rol' => 'Comercial',
                'name' => 'Usuario',
                'paterno' => 'Comercial',
                'materno' => 'PROINPA',
                'email' => 'comercial@proinpa.test',
                'celular' => '70000006',
            ],
            [
                'rol' => 'Agricultor',
                'name' => 'Productor',
                'paterno' => 'Agricultor',
                'materno' => 'Prueba',
                'email' => 'agricultor@proinpa.test',
                'celular' => '70000007',
            ],
        ];

        foreach ($usuarios as $item) {
            $rol = Rol::where('nombre', $item['rol'])->first();

            if (! $rol) {
                $this->command?->warn("No existe el rol: {$item['rol']}");

                continue;
            }

            $data = [
                'name' => $item['name'],
                'email' => $item['email'],
                'password' => Hash::make($password),
            ];

            if (Schema::hasColumn('users', 'paterno')) {
                $data['paterno'] = $item['paterno'];
            }

            if (Schema::hasColumn('users', 'materno')) {
                $data['materno'] = $item['materno'];
            }

            if (Schema::hasColumn('users', 'celular')) {
                $data['celular'] = $item['celular'];
            }

            if (Schema::hasColumn('users', 'id_rol')) {
                $data['id_rol'] = $rol->id;
            }

            if (Schema::hasColumn('users', 'rol_id')) {
                $data['rol_id'] = $rol->id;
            }

            if (Schema::hasColumn('users', 'activo')) {
                $data['activo'] = true;
            }

            if (Schema::hasColumn('users', 'estado')) {
                $data['estado'] = 'activo';
            }

            if (Schema::hasColumn('users', 'email_verified_at')) {
                $data['email_verified_at'] = now();
            }

            User::updateOrCreate(
                ['email' => $item['email']],
                $data
            );
        }

        $this->command?->info('Usuarios demo creados correctamente.');
        $this->command?->info("Contraseña para todos: {$password}");
    }
}
