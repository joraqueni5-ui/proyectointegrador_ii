<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Deja inspecciones listas para probar el módulo de Diagnósticos.
 *
 * Una inspección aparece al crear un diagnóstico solo si es de tipo Cultivo, está En_proceso o
 * Completada, tiene un cultivo ACTIVO y no tiene un diagnóstico pendiente de validar.
 *
 * Qué hace (no toca usuarios):
 *  - Marca como Cosechado el cultivo #1 (figuraba Activo pero ya tiene su cosecha registrada).
 *  - Crea 4 cultivos activos de la campaña 2026/27 en parcelas cuyo cultivo anterior ya se cosechó.
 *  - Crea 13 inspecciones recientes (octubre-noviembre 2026) sobre cultivos activos.
 *
 * Ejecutar: php artisan db:seed --class=InspeccionesParaDiagnosticoSeeder
 * Es idempotente. Simulación: DRY_RUN=1 ...
 */
class InspeccionesParaDiagnosticoSeeder extends Seeder
{
    private const HOY = '2026-11-05';

    public function run(): void
    {
        if (DB::table('cultivo_variedades')->where('fecha_siembra', '2026-10-02')->where('variedad_papa', 'Imilla Negra')->exists()) {
            $this->command?->warn('InspeccionesParaDiagnosticoSeeder ya se ejecutó: no se hace nada.');

            return;
        }
        mt_srand(2027);

        try {
            DB::transaction(function () {
                $this->ejecutar();
                if (getenv('DRY_RUN')) {
                    throw new RuntimeException('DRY_RUN');
                }
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'DRY_RUN') {
                throw $e;
            }
            $this->command?->info('Simulación terminada: no se guardó nada.');

            return;
        }
        $this->command?->info('Inspecciones para diagnóstico creadas.');
    }

    private function ejecutar(): void
    {
        // 1) Cultivo #1: ya tiene cosecha, no puede seguir "Activo".
        $c1 = DB::table('cultivo_variedades')->where('id', 1)->first();
        if ($c1 && $c1->estado === 'Activo' && DB::table('cosechas')->where('id_cultivo', 1)->exists()) {
            DB::table('cultivo_variedades')->where('id', 1)->update(['estado' => 'Cosechado', 'updated_at' => now()]);
        }

        // 2) Cuatro cultivos activos nuevos (campaña 2026/27)
        $nuevos = [
            ['CAR-01', 'Imilla Negra', '2026-10-02', 'Certificada'],
            ['ESC-01', 'Huaycha', '2026-10-09', 'Comunidad'],
            ['PPE-01', 'Sani Imilla', '2026-10-14', 'Certificada'],
            ['AYO-01', 'Runa', '2026-09-26', 'Propia'],
        ];
        $etapas = DB::table('etapas_fenologicas')->get();
        $cultivos = []; // id => [parcela, siembra]
        foreach ($nuevos as [$codigo, $variedad, $siembra, $procedencia]) {
            $parcela = DB::table('parcelas')->where('codigo_parcela', $codigo)->first();
            if (! $parcela || $parcela->estado !== 'Activa') {
                throw new RuntimeException("Parcela $codigo no disponible");
            }
            if (DB::table('cultivo_variedades')->where('id_parcela', $parcela->id)->where('estado', 'Activo')->exists()) {
                throw new RuntimeException("La parcela $codigo ya tiene un cultivo activo");
            }
            $dias = (int) Carbon::parse($siembra)->diffInDays(self::HOY);
            $id = DB::table('cultivo_variedades')->insertGetId([
                'id_parcela' => $parcela->id, 'variedad_papa' => $variedad, 'fecha_siembra' => $siembra,
                'cantidad_semilla_kg' => round($parcela->superficie_ha * mt_rand(1500, 2200) / 5) * 5,
                'procedencia_semilla' => $procedencia, 'id_etapa_fenologica' => $this->etapaPorDias($etapas, $dias),
                'fecha_estimada_cosecha' => Carbon::parse($siembra)->addDays(mt_rand(175, 200))->format('Y-m-d'),
                'observaciones' => 'Siembra de la campaña 2026/27.', 'estado' => 'Activo',
                'created_at' => Carbon::parse($siembra)->setTime(10, 0), 'updated_at' => Carbon::parse($siembra)->setTime(10, 0),
            ]);
            $cultivos[$id] = ['parcela' => $parcela->id, 'siembra' => $siembra];
        }
        // Cultivos activos que ya existían y son de esta campaña (45 a 48 y el #3).
        foreach (DB::table('cultivo_variedades')->whereIn('id', [3, 45, 46, 47, 48])->where('estado', 'Activo')->get() as $c) {
            $cultivos[$c->id] = ['parcela' => $c->id_parcela, 'siembra' => $c->fecha_siembra];
        }

        // 3) Inspecciones: una por cultivo activo + visitas de seguimiento (13 en total)
        $tecnicos = [2, 7, 10, 13, 19, 4, 9, 12];
        $programa = [8, 3, 6];
        $ids = array_keys($cultivos);
        $visitas = [];
        foreach ($ids as $id) {
            $visitas[] = [$id, 0];
        }
        foreach (array_slice($ids, 0, 5) as $id) {   // seguimiento en 5 cultivos
            $visitas[] = [$id, 1];
        }
        $visitas = array_slice($visitas, 0, 13);

        $obs = [
            'Emergencia despareja; se recomienda reponer los huecos y aporcar.',
            'Plantas con buen vigor; se observa humedad adecuada en el surco.',
            'Presencia de heladas leves en las mañanas; follaje sin daño visible.',
            'Control de malezas pendiente; se coordinó el deshierbe con el productor.',
            'Se revisó el estado de la semilla en brotación; sin síntomas de enfermedad.',
            'Monitoreo de plagas: sin presencia de gorgojo en la parcela.',
        ];
        $n = 0;
        foreach ($visitas as [$idCultivo, $seguimiento]) {
            $c = $cultivos[$idCultivo];
            $fecha = Carbon::parse('2026-10-14')->addDays($n * 2 + $seguimiento * 9)->min(Carbon::parse('2026-11-04'));
            if ($fecha->lt(Carbon::parse($c['siembra'])->addDays(3))) {
                $fecha = Carbon::parse($c['siembra'])->addDays(5);
            }
            $dias = (int) Carbon::parse($c['siembra'])->diffInDays($fecha);
            $estado = $n % 4 === 3 ? 'En_proceso' : 'Completada';
            $hora = mt_rand(8, 15);
            DB::table('inspecciones')->insert([
                'id_usuario' => $tecnicos[$n % count($tecnicos)], 'id_parcela' => $c['parcela'], 'id_cultivo' => $idCultivo,
                'fecha_visita' => $fecha->format('Y-m-d'), 'hora_visita' => sprintf('%02d:%02d:00', $hora, [0, 15, 30, 45][$n % 4]),
                'id_etapa_fenologica' => $this->etapaPorDias($etapas, $dias),
                'condicion_climatica' => ['Soleado', 'Nublado', 'Ventoso', 'Soleado', 'Helado', 'Mixto'][$n % 6],
                'temperatura_c' => round(mt_rand(40, 150) / 10, 1), 'humedad_relativa_pct' => mt_rand(30, 68),
                'pct_plantas_afectadas' => mt_rand(0, 6), 'observaciones' => $obs[$n % count($obs)],
                'estado' => $estado, 'tipo_inspeccion' => 'Cultivo', 'id_programado_por' => $programa[$n % 3],
                'id_validado_por' => null, 'fecha_inicio' => $fecha->copy()->setTime($hora, 0),
                'fecha_finalizacion' => $estado === 'Completada' ? $fecha->copy()->setTime($hora + 1, mt_rand(0, 40)) : null,
                'fecha_validacion' => null,
                'created_at' => $fecha->copy()->subDays(2)->setTime(9, 0), 'updated_at' => $fecha->copy()->setTime($hora + 1, 0),
            ]);
            $n++;
        }
    }

    private function etapaPorDias($etapas, int $dias): int
    {
        $mejor = null;
        $d0 = PHP_INT_MAX;
        foreach ($etapas as $e) {
            if (in_array($e->codigo, ['BBCH-00', 'BBCH-99'], true)) {
                continue;
            }
            $d = abs($dias - ($e->dias_desde_siembra_min + $e->dias_desde_siembra_max) / 2);
            if ($d < $d0) {
                $d0 = $d;
                $mejor = (int) $e->id;
            }
        }

        return $mejor;
    }
}
