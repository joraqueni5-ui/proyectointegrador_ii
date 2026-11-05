<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Permisos del sistema
        |--------------------------------------------------------------------------
        | Se mantienen permisos generales actuales para no romper controladores,
        | y se agregan permisos más específicos para discriminar vistas y acciones.
        */

        $permisos = [
            'ver_dashboard',
            'gestionar_usuarios',
            'gestionar_roles',

            'ver_productores',
            'gestionar_productores',
            'gestionar_parcelas',
            'registrar_cosechas',

            'ver_inspecciones',
            'crear_inspecciones',
            'ver_diagnosticos',
            'crear_diagnosticos',
            'validar_diagnosticos',
            'gestionar_enfermedades',
            'ver_alertas',
            'gestionar_alertas',

            'ver_inventario',
            'gestionar_inventario',
            'gestionar_productos',
            'gestionar_almacenes',
            'gestionar_movimientos',
            'ver_alertas_stock',

            'gestionar_ventas',
            'gestionar_cotizaciones',
            'gestionar_facturas',

            'ver_reportes',
            'exportar_reportes',
        ];

        $roles = [
            'Superadmin' => [
                'descripcion' => 'Acceso total al sistema. Administra usuarios, roles, módulos, reportes y configuración general.',
                'permisos' => $permisos,
                'activo' => true,
            ],

            'Administrador' => [
                'descripcion' => 'Administra la operación general del sistema, usuarios, productores, inventario, ventas, cotizaciones y reportes.',
                'permisos' => [
                    'ver_dashboard',
                    'gestionar_usuarios',

                    'gestionar_productores',
                    'gestionar_parcelas',
                    'registrar_cosechas',

                    'ver_inspecciones',
                    'crear_inspecciones',
                    'ver_diagnosticos',
                    'crear_diagnosticos',
                    'validar_diagnosticos',
                    'gestionar_enfermedades',
                    'ver_alertas',
                    'gestionar_alertas',

                    'ver_inventario',
                    'gestionar_inventario',
                    'gestionar_productos',
                    'gestionar_almacenes',
                    'gestionar_movimientos',
                    'ver_alertas_stock',

                    'gestionar_ventas',
                    'gestionar_cotizaciones',
                    'gestionar_facturas',

                    'ver_reportes',
                    'exportar_reportes',
                ],
                'activo' => true,
            ],

            'Agrónomo' => [
                'descripcion' => 'Usuario agrónomo encargado de seguimiento técnico y validación de diagnósticos.',
                'permisos' => [
                    'ver_dashboard',

                    'ver_productores',
                    'gestionar_productores',

                    'gestionar_parcelas',
                    'registrar_cosechas',

                    'ver_inspecciones',
                    'crear_inspecciones',

                    'ver_diagnosticos',
                    'crear_diagnosticos',
                    'validar_diagnosticos',
                    'gestionar_enfermedades',

                    'ver_alertas',
                    'gestionar_alertas',

                    'ver_reportes',
                    'exportar_reportes',
                ],
                'activo' => true,
            ],

            'Técnico' => [
                'descripcion' => 'Usuario técnico encargado del registro agrícola e inspecciones.',
                'permisos' => [
                    'ver_dashboard',

                    'ver_productores',
                    'gestionar_productores',

                    'gestionar_parcelas',
                    'registrar_cosechas',

                    'ver_inspecciones',
                    'crear_inspecciones',

                    'ver_diagnosticos',
                    'crear_diagnosticos',

                    'ver_alertas',
                    'gestionar_alertas',

                    'ver_reportes',
                ],
                'activo' => true,
            ],

            'Encargado de almacén' => [
                'descripcion' => 'Responsable del control físico del inventario, almacenes, movimientos y alertas de stock.',
                'permisos' => [
                    'ver_dashboard',

                    'ver_inventario',
                    'gestionar_inventario',
                    'gestionar_productos',
                    'gestionar_almacenes',
                    'gestionar_movimientos',
                    'ver_alertas_stock',

                    'ver_reportes',
                    'exportar_reportes',
                ],
                'activo' => true,
            ],

            'Comercial' => [
                'descripcion' => 'Usuario del área comercial. Gestiona cotizaciones, ventas y consulta disponibilidad de productos.',
                'permisos' => [
                    'ver_dashboard',

                    'ver_productores',
                    'ver_inventario',

                    'gestionar_ventas',
                    'gestionar_cotizaciones',
                    'gestionar_facturas',

                    'ver_reportes',
                    'exportar_reportes',
                ],
                'activo' => true,
            ],
            'Agricultor' => [
                'descripcion' => 'Usuario productor con acceso limitado a información técnica, diagnósticos y alertas relacionadas.',
                'permisos' => [
                    'ver_dashboard',
                    'ver_diagnosticos',
                    'ver_alertas',
                ],
                'activo' => true,
            ],

            'Visitante' => [
                'descripcion' => 'Rol informativo sin permisos de gestión. Se mantiene por compatibilidad, pero no debería usarse en operación normal.',
                'permisos' => [],
                'activo' => false,
            ],
        ];

        foreach ($roles as $nombre => $data) {
            Rol::updateOrCreate(
                ['nombre' => $nombre],
                [
                    'descripcion' => $data['descripcion'],
                    'permisos' => $data['permisos'],
                    'activo' => $data['activo'],
                ]
            );
        }
    }
}
