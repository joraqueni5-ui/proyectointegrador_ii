<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Inspecciones (agenda del técnico y transiciones de estado) y alertas (flujo de seguimiento).
 */
class InspeccionesAlertasTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioAgricola();
        Sanctum::actingAs($this->usuarios['Administrador']);
    }

    private function inspeccion(array $cambios = []): array
    {
        return $cambios + [
            'id_usuario' => $this->usuarios['Técnico']->id, 'id_parcela' => $this->idParcela, 'id_cultivo' => $this->idCultivo,
            'tipo_inspeccion' => 'Cultivo', 'fecha_visita' => now()->addDays(2)->toDateString(), 'hora_visita' => '09:00',
        ];
    }

    // ------------------------------------------------------------------ inspecciones

    public function test_un_tecnico_no_tiene_dos_inspecciones_a_la_misma_hora(): void
    {
        $this->postJson('/api/inspecciones', $this->inspeccion())->assertCreated();

        $r = $this->postJson('/api/inspecciones', $this->inspeccion())->assertStatus(422)->assertJsonValidationErrors('hora_visita');
        $this->assertStringContainsString('ya tiene otra inspección', $r->json('errors.hora_visita.0'));

        $this->postJson('/api/inspecciones', $this->inspeccion(['hora_visita' => '11:00']))->assertCreated();
        $this->postJson('/api/inspecciones', $this->inspeccion(['id_usuario' => $this->usuarios['Agrónomo']->id]))->assertCreated();
    }

    public function test_editar_una_inspeccion_no_choca_consigo_misma(): void
    {
        $id = $this->postJson('/api/inspecciones', $this->inspeccion())->assertCreated()->json('inspeccion.id');
        $otro = $this->postJson('/api/inspecciones', $this->inspeccion(['hora_visita' => '12:00']))->assertCreated()->json('inspeccion.id');

        $this->putJson("/api/inspecciones/{$id}", ['observaciones' => 'Sin cambios de horario'])->assertOk();
        $this->putJson("/api/inspecciones/{$otro}", ['hora_visita' => '09:00'])->assertStatus(422)->assertJsonValidationErrors('hora_visita');
    }

    public function test_porcentajes_temperatura_y_fecha_de_la_inspeccion(): void
    {
        foreach ([['pct_plantas_afectadas' => 101], ['pct_plantas_afectadas' => -1], ['humedad_relativa_pct' => 150], ['temperatura_c' => 80]] as $i => $malo) {
            $this->postJson('/api/inspecciones', $this->inspeccion($malo + ['hora_visita' => '1'.$i.':00']))
                ->assertStatus(422)->assertJsonValidationErrors(array_key_first($malo));
        }

        $this->postJson('/api/inspecciones', $this->inspeccion(['fecha_visita' => now()->subDays(200)->toDateString(), 'hora_visita' => '17:00']))
            ->assertStatus(422)->assertJsonValidationErrors('fecha_visita');

        $this->postJson('/api/inspecciones', $this->inspeccion(['pct_plantas_afectadas' => 35.5, 'humedad_relativa_pct' => 70, 'temperatura_c' => 12, 'hora_visita' => '18:00']))
            ->assertCreated();
    }

    public function test_transiciones_de_estado_de_la_inspeccion(): void
    {
        $id = $this->postJson('/api/inspecciones', $this->inspeccion())->assertCreated()->json('inspeccion.id');

        $this->patchJson("/api/inspecciones/{$id}/estado", ['estado' => 'Validada'])->assertStatus(422);
        $this->patchJson("/api/inspecciones/{$id}/estado", ['estado' => 'No_realizada'])->assertStatus(422)->assertJsonValidationErrors('motivo_no_realizada');

        $this->patchJson("/api/inspecciones/{$id}/estado", ['estado' => 'En_proceso'])->assertOk();
        $this->patchJson("/api/inspecciones/{$id}/estado", ['estado' => 'Completada'])->assertOk();
        $this->patchJson("/api/inspecciones/{$id}/estado", ['estado' => 'Validada'])->assertOk();

        $this->patchJson("/api/inspecciones/{$id}/estado", ['estado' => 'Pendiente'])->assertStatus(422);
        $this->putJson("/api/inspecciones/{$id}", ['observaciones' => 'Intento de edición'])->assertStatus(422);
    }

    // ------------------------------------------------------------------ alertas

    private function crearAlerta(): int
    {
        $diagnostico = DB::table('diagnosticos_cnn')->insertGetId([
            'id_inspeccion' => $this->idInspeccion, 'ruta_fotografia' => 'diagnosticos/prueba.jpg', 'porcentaje_confianza' => 90,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('alertas')->insertGetId([
            'id_diagnostico' => $diagnostico, 'nivel_riesgo' => 'Alto', 'titulo' => 'Alerta de prueba', 'mensaje' => 'Mensaje',
            'fecha_emision' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function plan(array $cambios = []): array
    {
        return $cambios + [
            'id_responsable' => $this->usuarios['Técnico']->id, 'plan_manejo' => 'Aplicar fungicida cada 7 días y retirar hojas afectadas.',
            'proxima_revision' => now()->addDays(3)->toDateString(),
        ];
    }

    public function test_el_seguimiento_de_una_alerta_sigue_el_orden_del_flujo(): void
    {
        $id = $this->crearAlerta();

        $this->patchJson("/api/alertas/{$id}/resolver", ['resultado_seguimiento' => 'Controlado', 'observacion_cierre' => 'Cierre sin pasar por el flujo.'])->assertStatus(422);
        $this->patchJson("/api/alertas/{$id}/iniciar-tratamiento", [])->assertStatus(422);
        $this->patchJson("/api/alertas/{$id}/planificar", $this->plan())->assertStatus(422);

        $this->patchJson("/api/alertas/{$id}/atender", [])->assertOk();
        $this->assertSame('En_revision', DB::table('alertas')->where('id', $id)->value('estado_seguimiento'));
    }

    public function test_flujo_completo_con_sus_validaciones(): void
    {
        $id = $this->crearAlerta();
        $this->patchJson("/api/alertas/{$id}/atender", [])->assertOk();

        $this->patchJson("/api/alertas/{$id}/planificar", $this->plan(['plan_manejo' => 'corto']))->assertStatus(422)->assertJsonValidationErrors('plan_manejo');
        $this->patchJson("/api/alertas/{$id}/planificar", $this->plan(['proxima_revision' => now()->subDay()->toDateString()]))->assertStatus(422)->assertJsonValidationErrors('proxima_revision');
        $this->patchJson("/api/alertas/{$id}/planificar", $this->plan(['id_responsable' => 99999]))->assertStatus(422)->assertJsonValidationErrors('id_responsable');
        $this->patchJson("/api/alertas/{$id}/planificar", $this->plan())->assertOk();

        $this->patchJson("/api/alertas/{$id}/iniciar-tratamiento", ['accion_tomada' => 'Primera aplicación'])->assertOk();

        $seg = ['accion_tomada' => 'Segunda aplicación', 'resultado_seguimiento' => 'Mejorando', 'progreso_seguimiento' => 60, 'proxima_revision' => now()->addDays(4)->toDateString()];
        $this->patchJson("/api/alertas/{$id}/seguimiento", ['progreso_seguimiento' => 150] + $seg)->assertStatus(422)->assertJsonValidationErrors('progreso_seguimiento');
        $this->patchJson("/api/alertas/{$id}/seguimiento", $seg)->assertOk();

        $this->patchJson("/api/alertas/{$id}/resolver", ['resultado_seguimiento' => 'Controlado'])->assertStatus(422)->assertJsonValidationErrors('observacion_cierre');
        $this->patchJson("/api/alertas/{$id}/resolver", ['resultado_seguimiento' => 'Controlado', 'observacion_cierre' => 'corta'])->assertStatus(422)->assertJsonValidationErrors('observacion_cierre');
        $this->patchJson("/api/alertas/{$id}/resolver", ['resultado_seguimiento' => 'Controlado', 'observacion_cierre' => 'Plaga controlada, sin plantas nuevas afectadas.'])->assertOk();

        $this->assertSame('Resuelta', DB::table('alertas')->where('id', $id)->value('estado_seguimiento'));
        $this->assertSame('Atendida', DB::table('alertas')->where('id', $id)->value('estado'));

        $this->patchJson("/api/alertas/{$id}/atender", [])->assertStatus(422);
        $this->patchJson("/api/alertas/{$id}/resolver", ['resultado_seguimiento' => 'Controlado', 'observacion_cierre' => 'Segundo cierre de la misma alerta.'])->assertStatus(422);
    }

    public function test_permisos_por_rol_en_inspecciones_y_alertas(): void
    {
        $id = $this->crearAlerta();

        Sanctum::actingAs($this->usuarios['Agricultor']);
        $this->patchJson("/api/alertas/{$id}/atender", [])->assertForbidden();
        $this->postJson('/api/inspecciones', $this->inspeccion())->assertForbidden();
    }
}
