<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Desempeño del modelo: métricas calculadas con diagnósticos validados (solo lectura).
 *
 * Datos de la prueba (clase predicha -> resultado de la validación):
 *   predijo TT:   4 confirmados + 2 corregidos a TTP        (6)
 *   predijo TTP:  3 confirmados + 1 corregido a SANA        (4)
 *   predijo SANA: 3 confirmados                             (3)
 *   + 2 rechazados, 1 pendiente y 1 diagnóstico manual.
 *
 * Matriz (filas = real, columnas = predicho):  TT:[4,0,0]  TTP:[2,3,0]  SANA:[0,1,3]
 */
class DesempenoModeloTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    private const V21 = 'PROINPA-ResNet50-v2.1-prototipo';

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioAgricola();

        foreach (['CNN-TT', 'CNN-TTP', 'CNN-SANA'] as $codigo) {
            $this->ids[$codigo] = (int) DB::table('enfermedades')->where('codigo_enfermedad', $codigo)->value('id');
        }

        $this->cargarDiagnosticos();
    }

    private function diag(string $pred, string $estado, ?string $final = null, float $conf = 90.0, string $fecha = '2026-10-05', string $version = self::V21, bool $manual = false): void
    {
        $nombres = ['CNN-TT' => 'Tizón Tardío', 'CNN-TTP' => 'Tizón Temprano', 'CNN-SANA' => 'Sana (Sin enfermedad)'];
        $top3 = [['clase' => 'x', 'nombre' => $nombres[$pred], 'codigo_enfermedad' => $pred, 'confianza' => $conf]];

        DB::table('diagnosticos_cnn')->insert([
            'id_inspeccion' => $this->idInspeccion,
            'id_enfermedad' => $this->ids[$estado === 'Corregido' ? $final : $pred],
            'ruta_fotografia' => 'diagnosticos/prueba.jpg',
            'porcentaje_confianza' => $conf,
            'top3_predicciones' => $manual ? null : json_encode($top3),
            'modelo_version' => $manual ? 'Manual-v1.0' : $version,
            'estado_validacion' => $estado,
            'created_at' => $fecha.' 10:00:00', 'updated_at' => $fecha.' 10:00:00',
        ]);
    }

    private function cargarDiagnosticos(): void
    {
        for ($i = 0; $i < 4; $i++) { $this->diag('CNN-TT', 'Confirmado', null, 90, '2026-09-10'); }
        for ($i = 0; $i < 2; $i++) { $this->diag('CNN-TT', 'Corregido', 'CNN-TTP', 60, '2026-09-20'); }
        for ($i = 0; $i < 3; $i++) { $this->diag('CNN-TTP', 'Confirmado', null, 90, '2026-10-05'); }
        $this->diag('CNN-TTP', 'Corregido', 'CNN-SANA', 60, '2026-10-06');
        for ($i = 0; $i < 3; $i++) { $this->diag('CNN-SANA', 'Confirmado', null, 90, '2026-10-07'); }
        for ($i = 0; $i < 2; $i++) { $this->diag('CNN-TT', 'Rechazado', null, 50, '2026-10-08'); }
        $this->diag('CNN-TT', 'Pendiente', null, 80, '2026-10-09');
        $this->diag('CNN-TT', 'Confirmado', null, 99, '2026-10-10', 'Manual-v1.0', true); // manual: no se evalúa
    }

    private function consultar(array $filtros = [], string $rol = 'Agrónomo')
    {
        Sanctum::actingAs($this->usuarios[$rol]);

        return $this->getJson('/api/diagnosticos/desempeno-modelo?'.http_build_query($filtros));
    }

    // ------------------------------------------------------------------ cálculo

    public function test_calcula_la_matriz_de_confusion(): void
    {
        $m = $this->consultar()->assertOk()->json('matriz');

        $this->assertSame(['CNN-SANA', 'CNN-TT', 'CNN-TTP'], $m['filas_reales']);
        // filas (real) y columnas (predicho) en orden SANA, TT, TTP
        $this->assertSame([[3, 0, 1], [0, 4, 0], [0, 2, 3]], $m['valores']);
    }

    public function test_calcula_precision_recall_f1_y_accuracy(): void
    {
        $r = $this->consultar()->assertOk();
        $clases = collect($r->json('por_clase'))->keyBy('codigo');

        $this->assertEqualsWithDelta(0.6667, $clases['CNN-TT']['precision'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $clases['CNN-TT']['recall'], 0.0001);
        $this->assertEqualsWithDelta(0.8, $clases['CNN-TT']['f1'], 0.0001);
        $this->assertEqualsWithDelta(0.75, $clases['CNN-TTP']['precision'], 0.0001);
        $this->assertEqualsWithDelta(0.6, $clases['CNN-TTP']['recall'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $clases['CNN-SANA']['precision'], 0.0001);
        $this->assertEqualsWithDelta(0.75, $clases['CNN-SANA']['recall'], 0.0001);

        $this->assertEqualsWithDelta(10 / 13, $r->json('metricas_globales.accuracy'), 0.0001);
        $this->assertEqualsWithDelta(0.7746, $r->json('metricas_globales.f1_macro'), 0.0001);
        $this->assertSame(13, $r->json('metricas_globales.muestras'));
    }

    public function test_calcula_los_porcentajes_y_excluye_los_manuales(): void
    {
        $r = $this->consultar()->assertOk()->json('resumen');

        $this->assertSame(16, $r['total_cnn']);          // 13 evaluados + 2 rechazados + 1 pendiente
        $this->assertSame(10, $r['confirmados']);
        $this->assertSame(3, $r['corregidos']);
        $this->assertSame(2, $r['rechazados']);
        $this->assertSame(1, $r['pendientes']);
        $this->assertSame(1, $r['diagnosticos_manuales_excluidos']);
        $this->assertEqualsWithDelta(62.5, $r['pct_confirmados'], 0.05);
        $this->assertEqualsWithDelta(18.8, $r['pct_corregidos'], 0.05);
        $this->assertEqualsWithDelta(12.5, $r['pct_rechazados'], 0.05);
    }

    public function test_compara_la_confianza_de_aciertos_y_errores(): void
    {
        $c = $this->consultar()->assertOk()->json('confianza');

        $this->assertEqualsWithDelta(90.0, $c['promedio_aciertos'], 0.01);
        $this->assertEqualsWithDelta(60.0, $c['promedio_errores'], 0.01);
        $this->assertSame(10, $c['n_aciertos']);
        $this->assertSame(3, $c['n_errores']);
    }

    public function test_arma_la_evolucion_mensual(): void
    {
        $meses = collect($this->consultar()->assertOk()->json('evolucion_mensual'))->keyBy('mes');

        // septiembre: 4 confirmados + 2 corregidos -> 66,7 %
        $this->assertSame(4, $meses['2026-09']['confirmados']);
        $this->assertSame(2, $meses['2026-09']['corregidos']);
        $this->assertEqualsWithDelta(66.7, $meses['2026-09']['aciertos_pct'], 0.05);
        // octubre: 6 confirmados + 1 corregido -> 85,7 %
        $this->assertSame(6, $meses['2026-10']['confirmados']);
        $this->assertEqualsWithDelta(85.7, $meses['2026-10']['aciertos_pct'], 0.05);
    }

    // ------------------------------------------------------------------ filtros

    public function test_filtra_por_rango_de_fechas(): void
    {
        $r = $this->consultar(['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31'])->assertOk();

        $this->assertSame(7, $r->json('metricas_globales.muestras')); // 3 + 1 + 3 evaluados de octubre
        $this->assertSame(0, $r->json('resumen.corregidos') - 1);
    }

    public function test_filtra_por_version_del_modelo(): void
    {
        DB::table('diagnosticos_cnn')->where('estado_validacion', 'Rechazado')->update(['modelo_version' => 'PROINPA-ResNet50-v1.0']);

        $r = $this->consultar(['modelo_version' => 'PROINPA-ResNet50-v1.0'])->assertOk();

        $this->assertSame(2, $r->json('resumen.total_cnn'));
        $this->assertSame(2, $r->json('resumen.rechazados'));
        $this->assertContains('PROINPA-ResNet50-v1.0', $r->json('modelos_disponibles'));
    }

    public function test_rechaza_filtros_invalidos(): void
    {
        $this->consultar(['fecha_desde' => '2026-10-10', 'fecha_hasta' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_hasta');
        $this->consultar(['fecha_desde' => '2020-01-01', 'fecha_hasta' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_hasta');
        $this->consultar(['fecha_desde' => '10/10/2026'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_desde');
        $this->consultar(['modelo_version' => 'version-que-no-existe'])
            ->assertStatus(422)->assertJsonValidationErrors('modelo_version');
    }

    public function test_sin_diagnosticos_no_falla_y_avisa(): void
    {
        DB::table('diagnosticos_cnn')->delete();

        $r = $this->consultar()->assertOk();

        $this->assertNull($r->json('metricas_globales.accuracy'));
        $this->assertSame([], $r->json('matriz.valores'));
        $this->assertNotEmpty($r->json('advertencias'));
    }

    // ------------------------------------------------------------------ permisos y solo lectura

    public function test_respeta_los_permisos_por_rol(): void
    {
        // ver_reportes o ver_diagnosticos
        foreach (['Superadmin', 'Administrador', 'Agrónomo', 'Técnico', 'Agricultor', 'Comercial', 'Encargado de almacén'] as $rol) {
            $this->consultar([], $rol)->assertOk();
        }

        // Sin ninguno de los dos permisos
        $visitante = User::create([
            'name' => 'Visitante', 'paterno' => 'Prueba', 'materno' => 'Test', 'email' => 'visita@prueba.test', 'celular' => '70000099',
            'password' => Hash::make('Clave-Segura-123'),
            'id_rol' => DB::table('roles')->where('nombre', 'Visitante')->value('id'), 'activo' => true,
        ]);
        Sanctum::actingAs($visitante);
        $this->getJson('/api/diagnosticos/desempeno-modelo')->assertForbidden();
        $this->get('/api/diagnosticos/desempeno-modelo/pdf')->assertForbidden();
    }

    public function test_exige_iniciar_sesion(): void
    {
        $this->getJson('/api/diagnosticos/desempeno-modelo')->assertUnauthorized();
    }

    public function test_es_solo_lectura(): void
    {
        $antes = DB::table('diagnosticos_cnn')->count();

        $this->consultar()->assertOk();

        $this->assertSame($antes, DB::table('diagnosticos_cnn')->count());
        Sanctum::actingAs($this->usuarios['Agrónomo']);
        $this->postJson('/api/diagnosticos/desempeno-modelo', [])->assertStatus(405);
    }

    // ------------------------------------------------------------------ PDF

    public function test_exporta_un_pdf(): void
    {
        Sanctum::actingAs($this->usuarios['Agrónomo']);

        $r = $this->get('/api/diagnosticos/desempeno-modelo/pdf?fecha_desde=2026-09-01&fecha_hasta=2026-10-31');

        $r->assertOk();
        $this->assertStringContainsString('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }
}
