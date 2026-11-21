<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Diagnósticos CNN: creación (con servicio CNN simulado), rechazos con motivo, validación de
 * archivos, permisos por rol y validación agronómica (incluida la doble validación).
 */
class DiagnosticoTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['services.cnn.url' => 'http://cnn.test']);
        $this->crearEscenarioAgricola();
    }

    private function enviar(?UploadedFile $foto = null, ?int $inspeccion = null, string $rol = 'Técnico')
    {
        Sanctum::actingAs($this->usuarios[$rol]);

        return $this->postJson('/api/diagnosticos', [
            'id_inspeccion' => $inspeccion ?? $this->idInspeccion,
            'fotografia' => $foto ?? UploadedFile::fake()->image('hoja.jpg', 640, 480),
        ]);
    }

    private function crearDiagnosticoPendiente(): int
    {
        Http::fake(['cnn.test/*' => Http::response($this->prediccionFalsa(), 200)]);

        return (int) $this->enviar()->assertCreated()->json('id');
    }

    // ------------------------------------------------------------------ creación

    public function test_crea_el_diagnostico_y_guarda_lo_que_calcula_la_cnn(): void
    {
        Http::fake(['cnn.test/*' => Http::response($this->prediccionFalsa('CNN-TT', 91.2), 200)]);

        $r = $this->enviar()->assertCreated();

        $d = DB::table('diagnosticos_cnn')->where('id', $r->json('id'))->first();
        $detalle = json_decode($d->detalle_inferencia, true);

        $this->assertSame('Pendiente', $d->estado_validacion);
        $this->assertEqualsWithDelta(91.2, (float) $d->porcentaje_confianza, 0.001);
        $this->assertEqualsWithDelta(0.71, $detalle['calidad_imagen']['nitidez'], 0.0001);
        $this->assertEqualsWithDelta(85.1, $detalle['validacion_prediccion']['margen'], 0.0001);
        $this->assertTrue($detalle['modelo']['calibrado']);
        $this->assertCount(3, json_decode($d->top3_predicciones, true));
        $this->assertSame('Completada', DB::table('inspecciones')->where('id', $this->idInspeccion)->value('estado'));
    }

    public function test_el_rechazo_de_la_cnn_llega_con_motivo_y_consejo_y_no_crea_nada(): void
    {
        Http::fake(['cnn.test/*' => Http::response([
            'detail' => 'La fotografía está demasiado borrosa.', 'codigo' => 'borrosa',
            'consejo' => 'Sostenga el celular firme y enfoque la hoja.',
        ], 422)]);

        $this->enviar()
            ->assertStatus(422)
            ->assertJsonPath('motivo.codigo', 'borrosa')
            ->assertJsonPath('motivo.consejo', 'Sostenga el celular firme y enfoque la hoja.')
            ->assertJsonPath('message', 'La fotografía está demasiado borrosa.');

        $this->assertSame(0, DB::table('diagnosticos_cnn')->count());
        $this->assertSame('En_proceso', DB::table('inspecciones')->where('id', $this->idInspeccion)->value('estado'));
    }

    public function test_si_el_servicio_cnn_esta_apagado_responde_503_en_espanol(): void
    {
        Http::fake(fn () => throw new ConnectionException('apagado'));

        $r = $this->enviar()->assertStatus(503);
        $this->assertStringContainsString('servicio de Inteligencia Artificial', $r->json('message'));
    }

    // ------------------------------------------------------------------ archivo

    public function test_rechaza_una_imagen_de_mas_de_5_mb(): void
    {
        Http::fake();

        $this->enviar(UploadedFile::fake()->image('grande.jpg', 800, 600)->size(5121))
            ->assertStatus(422)->assertJsonValidationErrors('fotografia');
        Http::assertNothingSent();
    }

    public function test_acepta_una_imagen_de_exactamente_5_mb(): void
    {
        Http::fake(['cnn.test/*' => Http::response($this->prediccionFalsa(), 200)]);

        $this->enviar(UploadedFile::fake()->image('limite.jpg', 800, 600)->size(5120))->assertCreated();
    }

    public function test_rechaza_una_imagen_demasiado_pequena(): void
    {
        Http::fake();

        $this->enviar(UploadedFile::fake()->image('pequena.jpg', 100, 100))
            ->assertStatus(422)->assertJsonValidationErrors('fotografia');
        Http::assertNothingSent();
    }

    public function test_rechaza_archivos_que_no_son_imagen(): void
    {
        Http::fake();

        $this->enviar(UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'))
            ->assertStatus(422)->assertJsonValidationErrors('fotografia');
    }

    // ------------------------------------------------------------------ inspección

    public function test_no_se_diagnostica_una_inspeccion_ya_validada(): void
    {
        Http::fake();
        DB::table('inspecciones')->where('id', $this->idInspeccion)->update(['estado' => 'Validada']);

        $this->enviar()->assertStatus(422)->assertJsonValidationErrors('id_inspeccion');
    }

    public function test_no_se_diagnostica_dos_veces_la_misma_inspeccion_pendiente(): void
    {
        $this->crearDiagnosticoPendiente();

        $this->enviar()->assertStatus(422)->assertJsonValidationErrors('id_inspeccion');
    }

    // ------------------------------------------------------------------ permisos

    public function test_solo_quien_puede_crear_diagnosticos_los_crea(): void
    {
        Http::fake();

        // El Agricultor no tiene "crear_diagnosticos": recibe 403 aunque el envío sea inválido.
        Sanctum::actingAs($this->usuarios['Agricultor']);
        $this->postJson('/api/diagnosticos', [])->assertForbidden();
        $this->enviar(null, null, 'Comercial')->assertForbidden();
    }

    public function test_solo_agronomo_administrador_o_superadmin_validan(): void
    {
        $id = $this->crearDiagnosticoPendiente();
        $cuerpo = ['estado_validacion' => 'Confirmado', 'nivel_severidad' => 'Leve', 'pct_plantas_afectadas' => 10];

        foreach (['Técnico', 'Agricultor', 'Comercial', 'Encargado de almacén'] as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);
            $this->patchJson("/api/diagnosticos/{$id}/validar", $cuerpo)->assertForbidden();
        }
        $this->assertSame('Pendiente', DB::table('diagnosticos_cnn')->where('id', $id)->value('estado_validacion'));

        Sanctum::actingAs($this->usuarios['Agrónomo']);
        $this->patchJson("/api/diagnosticos/{$id}/validar", $cuerpo)->assertOk();
    }

    // ------------------------------------------------------------------ validación agronómica

    public function test_la_severidad_es_obligatoria_al_confirmar_o_corregir(): void
    {
        $id = $this->crearDiagnosticoPendiente();
        Sanctum::actingAs($this->usuarios['Agrónomo']);

        $this->patchJson("/api/diagnosticos/{$id}/validar", ['estado_validacion' => 'Confirmado', 'pct_plantas_afectadas' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('nivel_severidad');

        $corregida = DB::table('enfermedades')->where('codigo_enfermedad', 'CNN-TTP')->value('id');
        $this->patchJson("/api/diagnosticos/{$id}/validar", ['estado_validacion' => 'Corregido', 'id_enfermedad' => $corregida, 'pct_plantas_afectadas' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('nivel_severidad');
    }

    public function test_rechazar_no_exige_severidad(): void
    {
        $id = $this->crearDiagnosticoPendiente();
        Sanctum::actingAs($this->usuarios['Agrónomo']);

        $this->patchJson("/api/diagnosticos/{$id}/validar", ['estado_validacion' => 'Rechazado'])->assertOk();
    }

    public function test_corregir_a_la_misma_enfermedad_no_se_permite(): void
    {
        $id = $this->crearDiagnosticoPendiente();
        Sanctum::actingAs($this->usuarios['Agrónomo']);
        $misma = DB::table('diagnosticos_cnn')->where('id', $id)->value('id_enfermedad');

        $this->patchJson("/api/diagnosticos/{$id}/validar", [
            'estado_validacion' => 'Corregido', 'id_enfermedad' => $misma, 'nivel_severidad' => 'Leve', 'pct_plantas_afectadas' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('id_enfermedad');
    }

    public function test_el_porcentaje_de_plantas_afectadas_debe_estar_entre_0_y_100(): void
    {
        $id = $this->crearDiagnosticoPendiente();
        Sanctum::actingAs($this->usuarios['Agrónomo']);

        foreach ([-1, 100.5, 'abc'] as $malo) {
            $this->patchJson("/api/diagnosticos/{$id}/validar", [
                'estado_validacion' => 'Confirmado', 'nivel_severidad' => 'Leve', 'pct_plantas_afectadas' => $malo,
            ])->assertStatus(422)->assertJsonValidationErrors('pct_plantas_afectadas');
        }
    }

    public function test_un_diagnostico_no_se_puede_validar_dos_veces(): void
    {
        $id = $this->crearDiagnosticoPendiente();
        $cuerpo = ['estado_validacion' => 'Confirmado', 'nivel_severidad' => 'Severa', 'pct_plantas_afectadas' => 40];

        // "Pestaña 1" y "pestaña 2" intentan validar el mismo diagnóstico.
        Sanctum::actingAs($this->usuarios['Agrónomo']);
        $this->patchJson("/api/diagnosticos/{$id}/validar", $cuerpo)->assertOk();

        Sanctum::actingAs($this->usuarios['Administrador']);
        $this->patchJson("/api/diagnosticos/{$id}/validar", ['estado_validacion' => 'Rechazado'])
            ->assertStatus(422);

        $d = DB::table('diagnosticos_cnn')->where('id', $id)->first();
        $this->assertSame('Confirmado', $d->estado_validacion);
        $this->assertSame($this->usuarios['Agrónomo']->id, (int) $d->validado_por);
        $this->assertSame(1, DB::table('alertas')->where('id_diagnostico', $id)->count());
    }
}
