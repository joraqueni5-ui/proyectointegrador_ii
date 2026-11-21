<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * GET /api/productores trae parcelas, superficie usada y parcelas con alerta con las mismas
 * consultas sin importar cuántos productores haya; el detalle suma parcelas y (solo con
 * permiso de ventas) el historial de compras.
 */
class ProductoresResumenTest extends TestCase
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
        return collect($this->getJson('/api/productores?todos=1')->assertOk()->json())->keyBy('id')->all();
    }

    private function parcela(int $idProductor, string $codigo, float $ha, string $estado = 'Activa'): int
    {
        return DB::table('parcelas')->insertGetId([
            'id_productor' => $idProductor, 'codigo_parcela' => $codigo, 'latitud' => -16.3, 'longitud' => -68.5,
            'superficie_ha' => $ha, 'sistema_riego' => 'Secano', 'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function productorNuevo(string $ci): int
    {
        return DB::table('productores')->insertGetId([
            'id_comunidad' => $this->idComunidad, 'nombre_completo' => "Otro {$ci}", 'nombres' => 'Otro', 'apellido_paterno' => $ci,
            'carnet_identidad' => $ci, 'ci_extension' => 'LP', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_totales_por_productor(): void
    {
        $this->parcela($this->idProductor, 'B-02', 2);
        $this->parcela($this->idProductor, 'B-03', 9, 'Inactiva'); // no cuenta
        $sin = $this->productorNuevo('7777777');

        $diagnostico = DB::table('diagnosticos_cnn')->insertGetId([
            'id_inspeccion' => $this->idInspeccion, 'ruta_fotografia' => 'x.jpg', 'porcentaje_confianza' => 90, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['Activa', 'Activa', 'Atendida'] as $estado) {
            DB::table('alertas')->insert([
                'id_diagnostico' => $diagnostico, 'nivel_riesgo' => 'Alto', 'titulo' => 'A', 'mensaje' => 'M',
                'fecha_emision' => now()->toDateString(), 'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $lista = $this->listar();

        $this->assertSame(2, $lista[$this->idProductor]['parcelas_count']);
        $this->assertEquals(3.5, $lista[$this->idProductor]['superficie_parcelas_ha']);
        $this->assertSame(1, $lista[$this->idProductor]['parcelas_con_alerta']);

        $this->assertSame(0, $lista[$sin]['parcelas_count']);
        $this->assertEquals(0, $lista[$sin]['superficie_parcelas_ha']);
        $this->assertSame(0, $lista[$sin]['parcelas_con_alerta']);
    }

    public function test_el_listado_paginado_tambien_trae_los_totales(): void
    {
        $r = $this->getJson('/api/productores?per_page=5')->assertOk();

        $this->assertSame(1, $r->json('data.0.parcelas_count'));
    }

    public function test_hace_las_mismas_consultas_con_pocos_o_muchos_productores(): void
    {
        $this->listar();

        $this->conteo = 0;
        $this->contando = true;
        $this->listar();
        $pocos = $this->conteo;

        for ($i = 0; $i < 12; $i++) {
            $id = $this->productorNuevo('80000'.$i);
            $this->parcela($id, "P-{$i}", 1);
        }

        $this->conteo = 0;
        $this->listar();
        $this->contando = false;

        $this->assertSame($pocos, $this->conteo);
    }

    public function test_el_detalle_trae_parcelas_con_alertas_y_las_compras_solo_con_permiso_de_ventas(): void
    {
        DB::table('facturas')->insert([
            'id_productor' => $this->idProductor, 'id_usuario' => $this->usuarios['Comercial']->id, 'nro_factura' => 'V-0001', 'tipo' => 'Venta',
            'fecha_emision' => now()->toDateString(), 'subtotal' => 100, 'descuento' => 0, 'monto_total' => 100, 'estado' => 'Pagada',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Administrador (tiene gestionar_ventas): ve las compras
        $r = $this->getJson("/api/productores/{$this->idProductor}")->assertOk();
        $this->assertCount(1, $r->json('facturas'));
        $this->assertSame(0, $r->json('parcelas.0.alertas_activas'));

        // Agrónomo (gestiona productores, no ventas): no recibe el historial de compras
        Sanctum::actingAs($this->usuarios['Agrónomo']);
        $r = $this->getJson("/api/productores/{$this->idProductor}")->assertOk();
        $this->assertArrayNotHasKey('facturas', $r->json());
        $this->assertCount(1, $r->json('parcelas'));
    }
}
