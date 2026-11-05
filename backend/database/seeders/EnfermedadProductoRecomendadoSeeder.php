<?php

namespace Database\Seeders;

use App\Models\Enfermedad;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnfermedadProductoRecomendadoSeeder extends Seeder
{
    public function run(): void
    {
        $relaciones = [
            'Tizón Tardío' => [
                [
                    'producto' => 'Mancozeb',
                    'tipo_recomendacion' => 'Curativo',
                    'dosis_recomendada' => 'Según ficha técnica del producto',
                    'prioridad' => 'Alta',
                ],
                [
                    'producto' => 'Metalaxil',
                    'tipo_recomendacion' => 'Curativo',
                    'dosis_recomendada' => 'Según ficha técnica del producto',
                    'prioridad' => 'Alta',
                ],
            ],

            'Tizón Temprano' => [
                [
                    'producto' => 'Clorotalonil',
                    'tipo_recomendacion' => 'Control',
                    'dosis_recomendada' => 'Según ficha técnica del producto',
                    'prioridad' => 'Media',
                ],
                [
                    'producto' => 'Mancozeb',
                    'tipo_recomendacion' => 'Control',
                    'dosis_recomendada' => 'Según ficha técnica del producto',
                    'prioridad' => 'Media',
                ],
            ],

            'Marchitez Bacteriana' => [
                [
                    'producto' => 'Semilla certificada',
                    'tipo_recomendacion' => 'Preventivo',
                    'dosis_recomendada' => 'Usar semilla certificada en la siguiente campaña',
                    'prioridad' => 'Alta',
                ],
            ],

            'Rizoctoniasis' => [
                [
                    'producto' => 'Carbendazim',
                    'tipo_recomendacion' => 'Control',
                    'dosis_recomendada' => 'Tratamiento preventivo de semilla',
                    'prioridad' => 'Alta',
                ],
            ],
        ];

        foreach ($relaciones as $nombreEnfermedad => $items) {
            $enfermedad = Enfermedad::where('nombre_comun', $nombreEnfermedad)->first();

            if (! $enfermedad) {
                continue;
            }

            foreach ($items as $item) {
                $producto = Producto::where('nombre_producto', 'ILIKE', '%'.$item['producto'].'%')->first();

                if (! $producto) {
                    continue;
                }

                DB::table('enfermedad_producto_recomendados')->updateOrInsert(
                    [
                        'id_enfermedad' => $enfermedad->id,
                        'id_producto' => $producto->id,
                    ],
                    [
                        'tipo_recomendacion' => $item['tipo_recomendacion'],
                        'dosis_recomendada' => $item['dosis_recomendada'],
                        'prioridad' => $item['prioridad'],
                        'activo' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
