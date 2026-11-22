<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Parcelas: resumen sanitario en una consulta (foto, alerta, visita), línea de tiempo, producción por
 * campaña, código automático y nombre obligatorio en parcelas nuevas.
 */
class ParcelasResumenTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    private int $conteo = 0;

    private bool $contando = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioAgricola();
        Sanctum::actingAs($this->usuarios['Administrador']);

        DB::listen(function () {
            if ($this->contando) {
                $this->conteo++;
            }
        });
    }

    private function parcela(string $codigo, string $estado = 'Activa', ?int $idProductor = null): int
    {
        return DB::table('parcelas')->insertGetId([
            'id_productor' => $idProductor ?? $this->idProductor, 'codigo_parcela' => $codigo, 'nombre_referencial' => "Lote {$codigo}",
            'latitud' => -16.3, 'longitud' => -68.5, 'superficie_ha' => 0.5, 'sistema_riego' => 'Secano', 'estado' => $estado,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function visita(int $idParcela, int $diasAtras, string $estado = 'Completada'): int
    {
        return DB::table('inspecciones')->insertGetId([
            'id_usuario' => $this->usuarios['Técnico']->id, 'id_parcela' => $idParcela, 'fecha_visita' => now()->subDays($diasAtras)->toDateString(),
            'hora_visita' => '10:00:00', 'estado' => $estado, 'tipo_inspeccion' => 'Cultivo', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function diagnostico(int $idInspeccion, string $codigoEnfermedad, string $foto, ?string $fecha = null): int
    {
        $enfermedad = DB::table('enfermedades')->where('codigo_enfermedad', $codigoEnfermedad)->value('id');

        return DB::table('diagnosticos_cnn')->insertGetId([
            'id_inspeccion' => $idInspeccion, 'id_enfermedad' => $enfermedad, 'ruta_fotografia' => $foto, 'porcentaje_confianza' => 88.5,
            'estado_validacion' => 'Confirmado', 'created_at' => $fecha ?? now(), 'updated_at' => now(),
        ]);
    }

    private function alerta(int $idDiagnostico, string $estado, int $puntaje): void
    {
        DB::table('alertas')->insert([
            'id_diagnostico' => $idDiagnostico, 'nivel_riesgo' => 'Alto', 'titulo' => 'Alerta de prueba', 'mensaje' => 'M',
            'fecha_emision' => now()->toDateString(), 'estado' => $estado, 'puntaje_prioridad' => $puntaje, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function resumen(): array
    {
        return collect($this->getJson('/api/parcelas?resumen=1')->assertOk()->json())->keyBy('id')->all();
    }

    // ------------------------------------------------------------------ resumen sanitario

    public function test_clasifica_cada_parcela_por_su_estado_sanitario(): void
    {
        $sana = $this->parcela('SANA-01');
        $this->visita($sana, 5);

        $vieja = $this->parcela('VIEJA-01');
        $this->visita($vieja, 45);

        $nunca = $this->parcela('NUNCA-01');

        $descanso = $this->parcela('DESC-01', 'En_descanso');
        $this->visita($descanso, 2);

        $conAlerta = $this->parcela('ALERTA-01');
        $insp = $this->visita($conAlerta, 3);
        $d = $this->diagnostico($insp, 'CNN-TT', 'diagnosticos/tizon.jpg');
        $this->alerta($d, 'Activa', 85);
        $this->alerta($d, 'Atendida', 99); // cerrada: no cuenta

        $r = $this->resumen();

        $this->assertSame('sana', $r[$sana]['estado_sanitario']);
        $this->assertSame(5, $r[$sana]['dias_sin_visita']);
        $this->assertSame('sin_visita', $r[$vieja]['estado_sanitario']);
        $this->assertSame('sin_visita', $r[$nunca]['estado_sanitario']);
        $this->assertNull($r[$nunca]['dias_sin_visita']);
        $this->assertSame('descanso', $r[$descanso]['estado_sanitario']);
        $this->assertSame('alerta', $r[$conAlerta]['estado_sanitario']);
        $this->assertSame(1, $r[$conAlerta]['alertas_activas']);
        $this->assertSame(85, $r[$conAlerta]['alerta_puntaje']);
        $this->assertSame('Tizón Tardío', $r[$conAlerta]['alerta_enfermedad']);
    }

    public function test_trae_la_foto_del_ultimo_diagnostico(): void
    {
        $id = $this->parcela('FOTO-01');
        $insp = $this->visita($id, 10);
        $this->diagnostico($insp, 'CNN-TT', 'diagnosticos/vieja.jpg', now()->subDays(8));
        $this->diagnostico($insp, 'CNN-SANA', 'diagnosticos/reciente.jpg', now()->subDay());

        $fila = $this->resumen()[$id];

        $this->assertSame('diagnosticos/reciente.jpg', $fila['ultima_foto']);
        $this->assertSame('Sana (Sin enfermedad)', $fila['ultimo_diagnostico_nombre']);
        $this->assertSame('Confirmado', $fila['ultimo_diagnostico_estado']);
    }

    public function test_sin_resumen_el_listado_no_cambia(): void
    {
        $fila = collect($this->getJson('/api/parcelas')->assertOk()->json())->firstWhere('id', $this->idParcela);

        $this->assertArrayNotHasKey('ultima_foto', $fila);
        $this->assertNull($fila['estado_sanitario']);
    }

    public function test_el_resumen_hace_las_mismas_consultas_con_pocas_o_muchas_parcelas(): void
    {
        $this->resumen(); // calentamiento

        $this->conteo = 0;
        $this->contando = true;
        $this->resumen();
        $pocas = $this->conteo;

        for ($i = 0; $i < 12; $i++) {
            $id = $this->parcela("MAS-{$i}");
            $insp = $this->visita($id, $i);
            $this->alerta($this->diagnostico($insp, 'CNN-TT', "f{$i}.jpg"), 'Activa', 50 + $i);
        }

        $this->conteo = 0;
        $this->resumen();
        $this->contando = false;

        $this->assertSame($pocas, $this->conteo, 'GET /parcelas?resumen=1 no debe hacer consultas por fila.');
    }

    // ------------------------------------------------------------------ detalle y línea de tiempo

    public function test_el_detalle_trae_la_produccion_por_campana(): void
    {
        foreach ([['2026-08-10', 3], ['2026-10-01', 1.5], ['2026-06-15', 2]] as [$fecha, $t]) {
            DB::table('cosechas')->insert([
                'id_parcela' => $this->idParcela, 'id_cultivo' => $this->idCultivo, 'id_usuario' => $this->usuarios['Administrador']->id, 'fecha_cosecha' => $fecha,
                'cantidad_toneladas' => $t, 'rendimiento_t_ha' => 0, 'calidad' => 'Primera', 'destino' => 'Venta', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $prod = collect($this->getJson("/api/parcelas/{$this->idParcela}")->assertOk()->json('produccion_por_campana'))->keyBy('campana');

        $this->assertEquals(4.5, $prod['2026–2027']['toneladas']);
        $this->assertSame(2, $prod['2026–2027']['cosechas']);
        $this->assertEquals(3.0, $prod['2026–2027']['rendimiento_t_ha']); // 4,5 t / 1,5 ha
        $this->assertEquals(2.0, $prod['2025–2026']['toneladas']);
    }

    public function test_linea_de_tiempo_ordenada_con_filtros_y_consultas_fijas(): void
    {
        $insp = $this->visita($this->idParcela, 4);
        $d = $this->diagnostico($insp, 'CNN-TT', 'f.jpg', now()->subDays(4));
        $this->alerta($d, 'Activa', 70);

        $todo = $this->getJson("/api/parcelas/{$this->idParcela}/linea-tiempo")->assertOk()->json();
        $tipos = array_column($todo, 'tipo');

        $this->assertContains('siembra', $tipos);
        $this->assertContains('inspeccion', $tipos);
        $this->assertContains('diagnostico', $tipos);
        $this->assertContains('alerta', $tipos);

        $fechas = array_column($todo, 'fecha');
        $ordenadas = $fechas;
        rsort($ordenadas);
        $this->assertSame($ordenadas, $fechas, 'Debe venir del más reciente al más antiguo.');

        $soloAlertas = $this->getJson("/api/parcelas/{$this->idParcela}/linea-tiempo?tipos[]=alerta")->assertOk()->json();
        $this->assertSame(['alerta'], array_unique(array_column($soloAlertas, 'tipo')));

        $this->getJson("/api/parcelas/{$this->idParcela}/linea-tiempo?tipos[]=otro")->assertStatus(422);
        $this->getJson("/api/parcelas/{$this->idParcela}/linea-tiempo?desde=2026-02-01&hasta=2026-01-01")->assertStatus(422);

        // mismas consultas con pocos o muchos eventos
        $ruta = "/api/parcelas/{$this->idParcela}/linea-tiempo";
        $this->getJson($ruta);
        $this->conteo = 0;
        $this->contando = true;
        $this->getJson($ruta);
        $pocas = $this->conteo;

        for ($i = 0; $i < 10; $i++) {
            $this->alerta($this->diagnostico($this->visita($this->idParcela, 20 + $i), 'CNN-TT', "g{$i}.jpg"), 'Activa', 40);
        }

        $this->conteo = 0;
        $this->getJson($ruta);
        $this->contando = false;

        $this->assertSame($pocas, $this->conteo);
    }

    public function test_linea_de_tiempo_exige_permiso(): void
    {
        Sanctum::actingAs($this->usuarios['Comercial']);

        $this->getJson("/api/parcelas/{$this->idParcela}/linea-tiempo")->assertForbidden();
    }

    // ------------------------------------------------------------------ código automático y nombre

    public function test_codigo_automatico_con_comunidad_iniciales_y_correlativo(): void
    {
        DB::table('comunidades')->where('id', $this->idComunidad)->update(['nombre_comunidad' => 'Ñuñoa Álta']);

        $this->getJson("/api/parcelas/siguiente-codigo?id_productor={$this->idProductor}")
            ->assertOk()->assertJson(['codigo' => 'NUN-JMQ-01', 'prefijo' => 'NUN-JMQ-']);

        $this->parcela('NUN-JMQ-01');
        $this->parcela('NUN-JMQ-05');
        $this->parcela('NUN-JMQ-ZZ'); // no numérico: se ignora

        $this->getJson("/api/parcelas/siguiente-codigo?id_productor={$this->idProductor}")->assertOk()->assertJson(['codigo' => 'NUN-JMQ-06']);
    }

    public function test_codigo_automatico_valida_y_respeta_permisos(): void
    {
        $this->getJson('/api/parcelas/siguiente-codigo')->assertStatus(422)->assertJsonValidationErrors('id_productor');
        $this->getJson('/api/parcelas/siguiente-codigo?id_productor=99999')->assertStatus(422);

        Sanctum::actingAs($this->usuarios['Agricultor']);
        $this->getJson("/api/parcelas/siguiente-codigo?id_productor={$this->idProductor}")->assertForbidden();
    }

    public function test_el_nombre_es_obligatorio_al_crear_pero_no_al_editar_una_parcela_antigua(): void
    {
        $base = ['id_productor' => $this->idProductor, 'codigo_parcela' => 'NOM-01', 'latitud' => -16.3, 'longitud' => -68.5, 'superficie_ha' => 0.5, 'sistema_riego' => 'Secano'];

        $r = $this->postJson('/api/parcelas', $base)->assertStatus(422)->assertJsonValidationErrors('nombre_referencial');
        $this->assertStringContainsString('Escribe un nombre', $r->json('errors.nombre_referencial.0'));

        $this->postJson('/api/parcelas', $base + ['nombre_referencial' => '   '])->assertStatus(422)->assertJsonValidationErrors('nombre_referencial');
        $this->postJson('/api/parcelas', $base + ['nombre_referencial' => 'Lote Nuevo'])->assertCreated();

        // parcela antigua sin nombre: se puede editar otro dato sin pedirle nombre
        DB::table('parcelas')->where('id', $this->idParcela)->update(['nombre_referencial' => null]);
        $this->putJson("/api/parcelas/{$this->idParcela}", ['observaciones' => 'Sin cambios de nombre'])->assertOk();
    }
}
