<?php

namespace Tests\Feature;

use App\Support\Campana;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

class CampanaTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    public function test_campana_de_julio_a_junio_por_defecto(): void
    {
        $this->assertSame(7, Campana::mesInicio());

        foreach ([
            ['2025-07-01', '2025–2026', '2025-07-01', '2026-06-30'],
            ['2026-06-30', '2025–2026', '2025-07-01', '2026-06-30'],
            ['2026-07-01', '2026–2027', '2026-07-01', '2027-06-30'],
            ['2025-12-31', '2025–2026', '2025-07-01', '2026-06-30'],
            ['2026-01-01', '2025–2026', '2025-07-01', '2026-06-30'],
        ] as [$fecha, $etiqueta, $desde, $hasta]) {
            $r = Campana::resumenDe($fecha);

            $this->assertSame($etiqueta, $r['etiqueta'], $fecha);
            $this->assertSame($desde, $r['desde'], $fecha);
            $this->assertSame($hasta, $r['hasta'], $fecha);
        }
    }

    public function test_el_mes_de_inicio_es_configurable(): void
    {
        config(['agricola.mes_inicio_campana' => 8]);
        $this->assertSame('2025–2026', Campana::etiquetaDe('2026-07-31'));
        $this->assertSame('2026–2027', Campana::etiquetaDe('2026-08-01'));

        config(['agricola.mes_inicio_campana' => 1]);
        $this->assertSame('2026', Campana::etiquetaDe('2026-11-21'));
        $this->assertSame('2026-12-31', Campana::finDe('2026-03-01')->toDateString());

        config(['agricola.mes_inicio_campana' => 99]); // valor inválido: se acota a 12
        $this->assertSame(12, Campana::mesInicio());
    }

    public function test_el_endpoint_de_configuracion_exige_sesion_y_devuelve_la_campana(): void
    {
        $this->getJson('/api/configuracion/agricola')->assertUnauthorized();

        $this->crearRolesYUsuarios();
        Sanctum::actingAs($this->usuarios['Técnico']);

        $r = $this->getJson('/api/configuracion/agricola')->assertOk();

        $this->assertSame(7, $r->json('mes_inicio_campana'));
        $this->assertSame(Campana::etiquetaDe(), $r->json('campana_actual.etiqueta'));
    }
}
