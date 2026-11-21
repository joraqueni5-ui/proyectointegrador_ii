<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * GET /api/comunidades?todos=1 trae productores, parcelas, superficie y parcelas con alerta
 * por comunidad, con las mismas consultas sin importar cuántas comunidades haya.
 */
class ComunidadesResumenTest extends TestCase
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

    private function listar(): array
    {
        return collect($this->getJson('/api/comunidades?todos=1')->assertOk()->json())->keyBy('id')->all();
    }

    private function crearAlerta(int $idInspeccion, string $estado): void
    {
        $diagnostico = DB::table('diagnosticos_cnn')->insertGetId([
            'id_inspeccion' => $idInspeccion, 'ruta_fotografia' => 'x.jpg', 'porcentaje_confianza' => 90,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('alertas')->insert([
            'id_diagnostico' => $diagnostico, 'nivel_riesgo' => 'Alto', 'titulo' => 'A', 'mensaje' => 'M',
            'fecha_emision' => now()->toDateString(), 'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_totales_por_comunidad(): void
    {
        // La parcela de prueba mide 1,5 ha. Se agrega otra de 2 ha, una inactiva (no cuenta) y alertas.
        DB::table('parcelas')->insert([
            ['id_productor' => $this->idProductor, 'codigo_parcela' => 'B-02', 'latitud' => -16.3, 'longitud' => -68.5, 'superficie_ha' => 2, 'sistema_riego' => 'Secano', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now()],
            ['id_productor' => $this->idProductor, 'codigo_parcela' => 'B-03', 'latitud' => -16.3, 'longitud' => -68.5, 'superficie_ha' => 9, 'sistema_riego' => 'Secano', 'estado' => 'Inactiva', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->crearAlerta($this->idInspeccion, 'Activa');
        $this->crearAlerta($this->idInspeccion, 'Activa');   // misma parcela: cuenta una sola vez
        $this->crearAlerta($this->idInspeccion, 'Atendida'); // cerrada: no cuenta

        $vacia = DB::table('comunidades')->insertGetId([
            'nombre_comunidad' => 'Sin gente', 'municipio' => 'Pucarani', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $lista = $this->listar();
        $c = $lista[$this->idComunidad];

        $this->assertSame(1, $c['productores_count']);
        $this->assertSame(2, $c['parcelas_count']);
        $this->assertEquals(3.5, $c['superficie_total_ha']);
        $this->assertSame(1, $c['parcelas_con_alerta']);

        $this->assertSame(0, $lista[$vacia]['parcelas_count']);
        $this->assertEquals(0, $lista[$vacia]['superficie_total_ha']);
        $this->assertSame(0, $lista[$vacia]['parcelas_con_alerta']);
    }

    public function test_hace_las_mismas_consultas_con_pocas_o_muchas_comunidades(): void
    {
        $this->listar(); // calentamiento

        $this->conteo = 0;
        $this->contando = true;
        $this->listar();
        $pocas = $this->conteo;

        for ($i = 0; $i < 15; $i++) {
            DB::table('comunidades')->insert(['nombre_comunidad' => "Comunidad {$i}", 'municipio' => 'Batallas', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->conteo = 0;
        $this->listar();
        $this->contando = false;

        $this->assertSame($pocas, $this->conteo);
    }

    public function test_el_listado_comun_no_cambia(): void
    {
        $fila = collect($this->getJson('/api/comunidades')->assertOk()->json())->firstWhere('id', $this->idComunidad);

        $this->assertArrayNotHasKey('parcelas_count', $fila);
    }
}
