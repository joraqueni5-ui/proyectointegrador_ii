<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * "Desempeño del modelo" y "Reportes fitosanitarios" exigen ver_diagnosticos Y ver_reportes.
 * Con los roles actuales: Superadmin, Administrador, Agrónomo y Técnico (tienen ambos).
 * Agricultor (solo ver_diagnosticos), Comercial y Encargado de almacén (solo ver_reportes) quedan fuera.
 */
class PermisosReportesTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    private const ENDPOINTS = [
        'desempeño (vista)' => '/api/diagnosticos/desempeno-modelo',
        'desempeño (PDF)' => '/api/diagnosticos/desempeno-modelo/pdf',
        'fitosanitarios (vista)' => '/api/reportes-fitosanitarios',
        'fitosanitarios (Excel)' => '/api/reportes-fitosanitarios/excel',
        'fitosanitarios (PDF)' => '/api/reportes-fitosanitarios/pdf',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioAgricola();
    }

    public function test_roles_sin_ambos_permisos_reciben_403_en_todos_los_endpoints(): void
    {
        foreach (['Agricultor', 'Comercial', 'Encargado de almacén'] as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);

            foreach (self::ENDPOINTS as $nombre => $ruta) {
                $this->getJson($ruta)->assertForbidden("{$rol} no debe acceder a {$nombre}.");
            }
        }
    }

    public function test_roles_con_ambos_permisos_acceden_a_todos_los_endpoints(): void
    {
        foreach (['Superadmin', 'Administrador', 'Agrónomo', 'Técnico'] as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);

            foreach (self::ENDPOINTS as $nombre => $ruta) {
                $estado = $this->getJson($ruta)->getStatusCode();

                $this->assertNotContains($estado, [401, 403], "{$rol} debe acceder a {$nombre} (respondió {$estado}).");
                $this->assertLessThan(500, $estado, "{$rol}: {$nombre} respondió {$estado}.");
            }
        }
    }

    public function test_los_permisos_reales_de_cada_rol_son_los_esperados(): void
    {
        $con = fn (string $rol) => $this->usuarios[$rol]->tieneTodosLosPermisos(['ver_diagnosticos', 'ver_reportes']);

        $this->assertTrue($con('Agrónomo'));
        $this->assertTrue($con('Administrador'));
        $this->assertTrue($con('Técnico'));
        $this->assertFalse($con('Agricultor'));
        $this->assertFalse($con('Comercial'));
        $this->assertFalse($con('Encargado de almacén'));
    }

    public function test_el_reporte_comercial_sigue_disponible_para_comercial_y_almacen(): void
    {
        foreach (['Comercial', 'Encargado de almacén'] as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);

            $estado = $this->getJson('/api/reportes')->getStatusCode();

            $this->assertNotContains($estado, [401, 403], "{$rol} debe seguir viendo el reporte comercial (respondió {$estado}).");
            $this->assertLessThan(500, $estado);
        }
    }
}
