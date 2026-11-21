<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Garantiza que las pruebas corren en una base exclusiva y que el esquema completo
 * (todas las migraciones) se puede construir desde cero en ella.
 */
class BaseDePruebasTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_pruebas_usan_la_base_exclusiva_y_no_la_real(): void
    {
        $this->assertSame('proinpa_test', DB::connection()->getDatabaseName());
        $this->assertNotSame('backend_db', DB::connection()->getDatabaseName());
    }

    public function test_el_esquema_completo_se_construye_desde_cero(): void
    {
        foreach (['users', 'roles', 'productores', 'parcelas', 'cultivo_variedades', 'inspecciones',
            'diagnosticos_cnn', 'alertas', 'inventarios', 'movimientos', 'facturas', 'cotizaciones'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
        }
    }
}
