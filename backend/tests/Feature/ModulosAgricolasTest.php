<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Comunidades, productores, parcelas, cultivos y cosechas: cada validación con un caso válido
 * y otro inválido contra el endpoint real, más los permisos por rol.
 */
class ModulosAgricolasTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioAgricola();
        Sanctum::actingAs($this->usuarios['Administrador']);
    }

    // ------------------------------------------------------------------ comunidades

    public function test_comunidad_con_coordenadas_dentro_de_bolivia(): void
    {
        $base = ['nombre_comunidad' => 'Comunidad Nueva', 'municipio' => 'Achacachi'];

        $this->postJson('/api/comunidades', $base + ['latitud' => 40.4, 'longitud' => -3.7])
            ->assertStatus(422)->assertJsonValidationErrors(['latitud']);
        $this->postJson('/api/comunidades', $base + ['latitud' => -16.2, 'longitud' => 10])
            ->assertStatus(422)->assertJsonValidationErrors(['longitud']);
        $this->postJson('/api/comunidades', $base + ['latitud' => -16.2, 'longitud' => -68.1, 'altitud_msnm' => 9000])
            ->assertStatus(422)->assertJsonValidationErrors(['altitud_msnm']);

        $this->postJson('/api/comunidades', $base + ['latitud' => -16.2, 'longitud' => -68.1, 'altitud_msnm' => 3800])
            ->assertCreated();
    }

    // ------------------------------------------------------------------ productores

    private function productor(array $cambios = []): array
    {
        return $cambios + [
            'id_comunidad' => $this->idComunidad, 'nombres' => 'Pedro', 'apellido_paterno' => 'Choque', 'apellido_materno' => 'Lima',
            'carnet_identidad' => '7654321', 'ci_extension' => 'LP', 'telefono' => '71234567', 'sexo' => 'M',
            'fecha_nacimiento' => '1985-03-02', 'superficie_total_ha' => 3,
        ];
    }

    public function test_productor_valido_y_reglas_de_ci_edad_y_superficie(): void
    {
        $this->postJson('/api/productores', $this->productor())->assertCreated();

        $this->postJson('/api/productores', $this->productor())->assertStatus(422)->assertJsonValidationErrors('carnet_identidad');
        $this->postJson('/api/productores', $this->productor(['carnet_identidad' => '7654322', 'fecha_nacimiento' => now()->subYears(15)->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_nacimiento');
        $this->postJson('/api/productores', $this->productor(['carnet_identidad' => '7654323', 'fecha_nacimiento' => now()->addYear()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_nacimiento');
        $this->postJson('/api/productores', $this->productor(['carnet_identidad' => '7654324', 'superficie_total_ha' => 90000]))
            ->assertStatus(422)->assertJsonValidationErrors('superficie_total_ha');
        $this->postJson('/api/productores', $this->productor(['carnet_identidad' => '7654325', 'nombres' => '   ']))
            ->assertStatus(422)->assertJsonValidationErrors('nombres');
    }

    // ------------------------------------------------------------------ parcelas

    private function parcela(array $cambios = []): array
    {
        return $cambios + [
            'id_productor' => $this->idProductor, 'codigo_parcela' => 'NUEVA-01', 'nombre_referencial' => 'Parcela nueva', 'latitud' => -16.3, 'longitud' => -68.5,
            'sistema_riego' => 'Secano', 'superficie_ha' => 1,
        ];
    }

    public function test_parcela_valida_y_rangos(): void
    {
        $this->postJson('/api/parcelas', $this->parcela())->assertCreated();

        $this->postJson('/api/parcelas', $this->parcela(['codigo_parcela' => 'X-02', 'superficie_ha' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('superficie_ha');
        $this->postJson('/api/parcelas', $this->parcela(['codigo_parcela' => 'X-03', 'superficie_ha' => 600]))
            ->assertStatus(422)->assertJsonValidationErrors('superficie_ha');
        $this->postJson('/api/parcelas', $this->parcela(['codigo_parcela' => 'X-04', 'superficie_ha' => 0.123]))
            ->assertStatus(422)->assertJsonValidationErrors('superficie_ha');
        $this->postJson('/api/parcelas', $this->parcela(['codigo_parcela' => 'X-05', 'latitud' => 48.8]))
            ->assertStatus(422)->assertJsonValidationErrors('latitud');
        $this->postJson('/api/parcelas', $this->parcela(['codigo_parcela' => 'nueva-01']))
            ->assertStatus(422)->assertJsonValidationErrors('codigo_parcela');
    }

    public function test_la_suma_de_parcelas_no_supera_la_superficie_total_del_productor(): void
    {
        // El productor declara 5 ha y ya tiene una parcela de 1,5 ha: quedan 3,5 ha.
        $r = $this->postJson('/api/parcelas', $this->parcela(['codigo_parcela' => 'GRANDE-01', 'superficie_ha' => 4]))
            ->assertStatus(422)->assertJsonValidationErrors('superficie_ha');
        $this->assertStringContainsString('3,50', $r->json('errors.superficie_ha.0'));

        $this->postJson('/api/parcelas', $this->parcela(['codigo_parcela' => 'JUSTA-01', 'superficie_ha' => 3.5]))->assertCreated();

        // Editar la parcela existente respetando el total sí se puede; pasarse no.
        $this->putJson("/api/parcelas/{$this->idParcela}", ['superficie_ha' => 1.5])->assertOk();
        $this->putJson("/api/parcelas/{$this->idParcela}", ['superficie_ha' => 2])->assertStatus(422)->assertJsonValidationErrors('superficie_ha');
    }

    public function test_un_productor_sin_superficie_declarada_no_limita_las_parcelas(): void
    {
        DB::table('productores')->where('id', $this->idProductor)->update(['superficie_total_ha' => null]);

        $this->postJson('/api/parcelas', $this->parcela(['superficie_ha' => 50]))->assertCreated();
    }

    // ------------------------------------------------------------------ cultivos

    public function test_cultivo_no_se_duplica_en_la_misma_parcela_ni_se_siembra_a_futuro(): void
    {
        $this->postJson('/api/cultivo-variedades', ['id_parcela' => $this->idParcela, 'variedad_papa' => 'Waycha', 'fecha_siembra' => now()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('id_parcela');

        $libre = DB::table('parcelas')->insertGetId([
            'id_productor' => $this->idProductor, 'codigo_parcela' => 'LIBRE-01', 'latitud' => -16.3, 'longitud' => -68.5,
            'superficie_ha' => 0.5, 'sistema_riego' => 'Secano', 'estado' => 'Activa', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson('/api/cultivo-variedades', ['id_parcela' => $libre, 'variedad_papa' => 'Waycha', 'fecha_siembra' => now()->addDays(3)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_siembra');
        $this->postJson('/api/cultivo-variedades', ['id_parcela' => $libre, 'variedad_papa' => 'Waycha', 'fecha_siembra' => now()->toDateString(), 'cantidad_semilla_kg' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('cantidad_semilla_kg');
        $this->postJson('/api/cultivo-variedades', ['id_parcela' => $libre, 'variedad_papa' => 'Waycha', 'fecha_siembra' => now()->toDateString(), 'cantidad_semilla_kg' => 300])
            ->assertCreated();
    }

    // ------------------------------------------------------------------ cosechas

    private function cosecha(array $cambios = []): array
    {
        return $cambios + [
            'id_parcela' => $this->idParcela, 'id_cultivo' => $this->idCultivo, 'fecha_cosecha' => now()->toDateString(),
            'cantidad_toneladas' => 4.5, 'calidad' => 'Primera', 'destino' => 'Venta',
        ];
    }

    public function test_cosecha_fecha_cantidad_y_cultivo(): void
    {
        $this->postJson('/api/cosechas', $this->cosecha(['fecha_cosecha' => now()->subDays(90)->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_cosecha');
        $this->postJson('/api/cosechas', $this->cosecha(['fecha_cosecha' => now()->addDay()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_cosecha');
        $this->postJson('/api/cosechas', $this->cosecha(['cantidad_toneladas' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('cantidad_toneladas');
        $this->postJson('/api/cosechas', $this->cosecha(['cantidad_toneladas' => 50000]))
            ->assertStatus(422)->assertJsonValidationErrors('cantidad_toneladas');

        $this->postJson('/api/cosechas', $this->cosecha())->assertCreated();
        $this->assertSame('Cosechado', DB::table('cultivo_variedades')->where('id', $this->idCultivo)->value('estado'));

        $this->postJson('/api/cosechas', $this->cosecha())->assertStatus(422)->assertJsonValidationErrors('id_cultivo');
        $this->assertSame(1, DB::table('cosechas')->count());
    }

    // ------------------------------------------------------------------ permisos por rol

    public function test_permisos_por_rol_en_modulos_agricolas(): void
    {
        Sanctum::actingAs($this->usuarios['Agricultor']);

        $this->postJson('/api/comunidades', ['nombre_comunidad' => 'Zona Nueva', 'municipio' => 'Pucarani'])->assertForbidden();
        $this->postJson('/api/productores', $this->productor(['carnet_identidad' => '1010101']))->assertForbidden();
        $this->postJson('/api/parcelas', $this->parcela())->assertForbidden();
        $this->postJson('/api/cosechas', $this->cosecha())->assertForbidden();
    }
}
