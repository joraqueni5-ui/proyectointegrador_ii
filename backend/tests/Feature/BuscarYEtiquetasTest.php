<?php

namespace Tests\Feature;

use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Nombre legible de las parcelas (sin consultas extra) y búsqueda general (GET /api/buscar).
 */
class BuscarYEtiquetasTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

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

    private function nuevaParcela(?string $nombre, string $codigo): int
    {
        return DB::table('parcelas')->insertGetId([
            'id_productor' => $this->idProductor, 'codigo_parcela' => $codigo, 'nombre_referencial' => $nombre,
            'latitud' => -16.3, 'longitud' => -68.5, 'superficie_ha' => 0.1, 'sistema_riego' => 'Secano',
            'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private int $conteo = 0;

    private bool $contando = false;

    /** Cuenta las consultas SQL de una llamada (hace antes una llamada de calentamiento). */
    private function consultas(callable $llamada): int
    {
        $llamada(); // calentamiento: carga el rol del usuario y cachés de una sola vez

        $this->conteo = 0;
        $this->contando = true;
        $llamada();
        $this->contando = false;

        return $this->conteo;
    }

    // ------------------------------------------------------------------ etiquetas

    public function test_etiqueta_completa_en_el_listado_de_parcelas(): void
    {
        $fila = collect($this->getJson('/api/parcelas')->assertOk()->json())->firstWhere('id', $this->idParcela);

        $this->assertSame('Parcela de prueba', $fila['nombre_visible']);
        $this->assertSame('Parcela de prueba — Juan Mamani · Comunidad de prueba', $fila['etiqueta']);
        $this->assertFalse($fila['sin_nombre']);
    }

    public function test_parcela_sin_nombre_se_numera_entre_las_del_productor(): void
    {
        $segunda = $this->nuevaParcela(null, 'SIN-02');
        $tercera = $this->nuevaParcela('   ', 'SIN-03');

        $filas = collect($this->getJson('/api/parcelas')->json())->keyBy('id');

        $this->assertSame('Parcela 2 de Juan Mamani', $filas[$segunda]['nombre_visible']);
        $this->assertSame('Parcela 3 de Juan Mamani', $filas[$tercera]['nombre_visible']);
        $this->assertTrue($filas[$segunda]['sin_nombre']);
        $this->assertSame('Parcela 2 de Juan Mamani — Juan Mamani · Comunidad de prueba', $filas[$segunda]['etiqueta']);
    }

    public function test_sin_relaciones_cargadas_usa_solo_el_nombre_o_el_codigo(): void
    {
        $sinNombre = $this->nuevaParcela(null, 'SIN-02');

        $this->assertSame('Parcela de prueba', Parcela::find($this->idParcela)->etiqueta);
        $this->assertSame('SIN-02', Parcela::find($sinNombre)->nombre_visible);
        $this->assertSame('SIN-02', Parcela::find($sinNombre)->etiqueta);
    }

    public function test_el_listado_de_parcelas_hace_las_mismas_consultas_con_pocas_o_muchas_filas(): void
    {
        $pocas = $this->consultas(fn () => $this->getJson('/api/parcelas')->assertOk());

        for ($i = 0; $i < 12; $i++) {
            $this->nuevaParcela($i % 2 ? null : "Parcela {$i}", "MAS-{$i}");
        }

        $muchas = $this->consultas(fn () => $this->getJson('/api/parcelas')->assertOk());

        $this->assertSame($pocas, $muchas, 'GET /parcelas no debe hacer consultas por fila.');
    }

    // ------------------------------------------------------------------ búsqueda

    public function test_la_busqueda_exige_entre_2_y_80_caracteres(): void
    {
        $this->getJson('/api/buscar')->assertStatus(422)->assertJsonValidationErrors('q');
        $this->getJson('/api/buscar?q=a')->assertStatus(422)->assertJsonValidationErrors('q');
        $this->getJson('/api/buscar?q=%20%20a%20%20')->assertStatus(422);
        $this->getJson('/api/buscar?q='.str_repeat('x', 81))->assertStatus(422)->assertJsonValidationErrors('q');

        $this->getJson('/api/buscar?q=Ju')->assertOk();
    }

    public function test_encuentra_parcelas_productores_y_comunidades_con_etiqueta_legible(): void
    {
        $r = $this->getJson('/api/buscar?q=mamani')->assertOk();

        $this->assertSame('Parcela de prueba — Juan Mamani · Comunidad de prueba', $r->json('parcelas.0.etiqueta'));
        $this->assertSame('TEST-01', $r->json('parcelas.0.detalle'));
        $this->assertSame('Juan Mamani', $r->json('productores.0.etiqueta'));
        $this->assertStringContainsString('CI 1234567 LP', $r->json('productores.0.detalle'));

        $c = $this->getJson('/api/buscar?q=batallas')->assertOk();
        $this->assertSame('Comunidad de prueba', $c->json('comunidades.0.etiqueta'));
    }

    public function test_maximo_cinco_resultados_por_tipo(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            DB::table('productos')->insert([
                'codigo_producto' => "FUN-{$i}", 'nombre_producto' => "Fungicida Prueba {$i}", 'categoria' => 'Agroquimico',
                'unidad_medida' => 'L', 'precio_unitario' => 10, 'stock_minimo' => 0, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->getJson('/api/buscar?q=Fungicida%20Prueba')->assertOk()->assertJsonCount(5, 'productos');
    }

    public function test_los_comodines_de_like_se_buscan_como_texto_literal(): void
    {
        $r = $this->getJson('/api/buscar?q=%25%25')->assertOk();

        $this->assertEmpty($r->json('parcelas'));
        $this->assertEmpty($r->json('productores'));
    }

    public function test_cada_rol_solo_recibe_los_tipos_que_puede_ver(): void
    {
        Sanctum::actingAs($this->usuarios['Agricultor']);
        $this->assertSame([], $this->getJson('/api/buscar?q=Juan')->assertOk()->json());

        Sanctum::actingAs($this->usuarios['Encargado de almacén']);
        $tipos = array_keys($this->getJson('/api/buscar?q=Juan')->assertOk()->json());
        $this->assertSame(['productos'], $tipos);

        Sanctum::actingAs($this->usuarios['Superadmin']);
        $tipos = array_keys($this->getJson('/api/buscar?q=Juan')->assertOk()->json());
        sort($tipos);
        $this->assertSame(['comunidades', 'parcelas', 'productores', 'productos'], $tipos);
    }

    public function test_la_busqueda_requiere_sesion(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withHeaders(['Authorization' => ''])->getJson('/api/buscar?q=Juan')->assertUnauthorized();
    }

    public function test_la_busqueda_hace_las_mismas_consultas_con_pocas_o_muchas_coincidencias(): void
    {
        $pocas = $this->consultas(fn () => $this->getJson('/api/buscar?q=Parcela')->assertOk());

        for ($i = 0; $i < 10; $i++) {
            $this->nuevaParcela($i % 2 ? null : "Parcela extra {$i}", "BUS-{$i}");
        }

        $muchas = $this->consultas(fn () => $this->getJson('/api/buscar?q=Parcela')->assertOk()->assertJsonCount(5, 'parcelas'));

        $this->assertSame($pocas, $muchas, 'GET /buscar no debe hacer consultas por resultado.');
    }

    public function test_el_buscador_tiene_limite_de_uso_por_minuto(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/buscar?q=Juan')->assertOk();
        }

        $this->getJson('/api/buscar?q=Juan')->assertStatus(429);
    }
}
