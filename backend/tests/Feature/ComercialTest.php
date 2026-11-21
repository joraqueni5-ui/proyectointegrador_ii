<?php

namespace Tests\Feature;

use App\Models\Factura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;

/**
 * Inventario, movimientos, ventas y cotizaciones: stock nunca negativo, total calculado en el
 * servidor, una cotización no se convierte dos veces y permisos por rol.
 */
class ComercialTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    private int $idProducto;
    private int $idAlmacen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioAgricola();
        Sanctum::actingAs($this->usuarios['Administrador']);

        $this->idProducto = DB::table('productos')->insertGetId([
            'codigo_producto' => 'SEM-001', 'nombre_producto' => 'Semilla Imilla', 'categoria' => 'Semilla', 'unidad_medida' => 'kg',
            'precio_unitario' => 10, 'stock_minimo' => 5, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->idAlmacen = DB::table('almacenes')->insertGetId([
            'nombre_almacen' => 'Central', 'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->movimiento('Entrada', 100, 'Compra')->assertCreated();
    }

    private function movimiento(string $tipo, $cantidad, string $motivo = 'Venta')
    {
        return $this->postJson('/api/inventario/movimientos', [
            'id_producto' => $this->idProducto, 'id_almacen' => $this->idAlmacen, 'tipo' => $tipo, 'cantidad' => $cantidad, 'motivo' => $motivo,
        ]);
    }

    private function stock(): float
    {
        return (float) DB::table('inventarios')->where('id_producto', $this->idProducto)->where('id_almacen', $this->idAlmacen)->value('stock_actual');
    }

    private function documento(?array $detalles = null, array $cambios = []): array
    {
        return $cambios + [
            'id_productor' => $this->idProductor, 'metodo_pago' => 'Efectivo', 'tipo' => 'Venta',
            'detalles' => $detalles ?? [['id_producto' => $this->idProducto, 'id_almacen' => $this->idAlmacen, 'cantidad' => 10, 'precio_unitario' => 12.5]],
        ];
    }

    // ------------------------------------------------------------------ movimientos y stock

    public function test_una_salida_no_puede_dejar_el_stock_negativo(): void
    {
        $this->movimiento('Salida', 100)->assertCreated();
        $this->assertSame(0.0, $this->stock());

        $this->movimiento('Salida', 1)->assertStatus(422);
        $this->assertSame(0.0, $this->stock());
        $this->assertSame(2, DB::table('movimientos')->count());
    }

    public function test_salidas_seguidas_que_juntas_superan_el_stock_se_rechazan(): void
    {
        $this->movimiento('Salida', 60)->assertCreated();
        $this->movimiento('Salida', 60)->assertStatus(422);

        $this->assertSame(40.0, $this->stock());
    }

    public function test_una_venta_con_lineas_que_juntas_superan_el_stock_no_se_guarda_a_medias(): void
    {
        $linea = ['id_producto' => $this->idProducto, 'id_almacen' => $this->idAlmacen, 'cantidad' => 60, 'precio_unitario' => 10];

        $this->postJson('/api/ventas', $this->documento([$linea, $linea]))->assertStatus(422);

        $this->assertSame(100.0, $this->stock());
        $this->assertSame(0, Factura::count());
    }

    public function test_cantidad_de_movimiento_cero_negativa_o_con_muchos_decimales(): void
    {
        foreach ([0, -5, 'abc', 0.0001, 99999999] as $mala) {
            $this->movimiento('Entrada', $mala, 'Compra')->assertStatus(422)->assertJsonValidationErrors('cantidad');
        }
        $this->movimiento('Entrada', 2.5, 'Compra')->assertCreated();
        $this->assertSame(102.5, $this->stock());
    }

    public function test_el_motivo_debe_corresponder_al_tipo(): void
    {
        $this->movimiento('Salida', 1, 'Compra')->assertStatus(422);
        $this->movimiento('Entrada', 1, 'Venta')->assertStatus(422);
    }

    public function test_producto_con_precio_invalido(): void
    {
        $base = ['codigo_producto' => 'NUE-001', 'nombre_producto' => 'Producto nuevo', 'categoria' => 'Bioinsumo', 'unidad_medida' => 'lt'];

        $this->postJson('/api/inventario/productos', $base + ['precio_unitario' => -1])->assertStatus(422)->assertJsonValidationErrors('precio_unitario');
        $this->postJson('/api/inventario/productos', $base + ['precio_unitario' => 10.999])->assertStatus(422)->assertJsonValidationErrors('precio_unitario');
        $this->postJson('/api/inventario/productos', $base + ['precio_unitario' => 99999999])->assertStatus(422)->assertJsonValidationErrors('precio_unitario');
        $this->postJson('/api/inventario/productos', $base + ['precio_unitario' => 45.50])->assertCreated();
    }

    // ------------------------------------------------------------------ ventas

    public function test_el_total_de_la_venta_lo_calcula_el_servidor(): void
    {
        $r = $this->postJson('/api/ventas', $this->documento(null, ['descuento' => 5, 'monto_total' => 1, 'subtotal' => 1]))->assertCreated();

        // 10 x 12,50 = 125,00 - 5,00 de descuento = 120,00 (se ignora el monto enviado por el cliente)
        $this->assertEquals(125.0, (float) $r->json('subtotal'));
        $this->assertEquals(120.0, (float) $r->json('monto_total'));
        $this->assertSame(90.0, $this->stock());
    }

    public function test_venta_con_datos_invalidos(): void
    {
        $linea = fn (array $c) => [array_merge(['id_producto' => $this->idProducto, 'id_almacen' => $this->idAlmacen, 'cantidad' => 1, 'precio_unitario' => 10], $c)];

        $this->postJson('/api/ventas', $this->documento($linea(['precio_unitario' => 0])))->assertStatus(422)->assertJsonValidationErrors('detalles.0.precio_unitario');
        $this->postJson('/api/ventas', $this->documento($linea(['cantidad' => 0])))->assertStatus(422)->assertJsonValidationErrors('detalles.0.cantidad');
        $this->postJson('/api/ventas', $this->documento($linea(['precio_unitario' => 10.555])))->assertStatus(422)->assertJsonValidationErrors('detalles.0.precio_unitario');
        $this->postJson('/api/ventas', $this->documento($linea(['descuento_linea' => 50])))->assertStatus(422)->assertJsonValidationErrors('detalles.0.descuento_linea');
        $this->postJson('/api/ventas', $this->documento(null, ['descuento' => 9999]))->assertStatus(422)->assertJsonValidationErrors('descuento');
        $this->postJson('/api/ventas', $this->documento(null, ['fecha_emision' => now()->addDays(2)->toDateString()]))->assertStatus(422)->assertJsonValidationErrors('fecha_emision');
        $this->postJson('/api/ventas', $this->documento([]))->assertStatus(422)->assertJsonValidationErrors('detalles');
        $this->assertSame(0, Factura::count());
    }

    public function test_estados_de_una_venta_y_anulacion_que_repone_stock(): void
    {
        $id = $this->postJson('/api/ventas', $this->documento(null, ['estado' => 'Pendiente']))->assertCreated()->json('id');

        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'Pagada'])->assertOk();
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'Pendiente'])->assertStatus(422);

        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'Anulada'])->assertOk();
        $this->assertSame(100.0, $this->stock());

        // Anulada: no se anula de nuevo (no repone stock dos veces) ni cambia de estado
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'Anulada'])->assertStatus(422);
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'Pagada'])->assertStatus(422);
        $this->assertSame(100.0, $this->stock());
    }

    // ------------------------------------------------------------------ cotizaciones

    private function cotizacion(array $cambios = []): int
    {
        return $this->postJson('/api/cotizaciones', $this->documento(null, $cambios))->assertCreated()->json('id');
    }

    public function test_una_cotizacion_aprobada_no_se_convierte_dos_veces(): void
    {
        $id = $this->cotizacion();
        $this->patchJson("/api/cotizaciones/{$id}/aprobar")->assertOk();

        $this->postJson("/api/cotizaciones/{$id}/convertir-venta")->assertOk();
        $this->postJson("/api/cotizaciones/{$id}/convertir-venta")->assertStatus(422);

        $this->assertSame(1, Factura::count());
        $this->assertSame(90.0, $this->stock());
    }

    public function test_no_se_aprueba_ni_convierte_una_cotizacion_vencida(): void
    {
        $id = $this->cotizacion(['fecha_vencimiento' => now()->addDay()->toDateString()]);
        DB::table('cotizaciones')->where('id', $id)->update(['fecha_vencimiento' => now()->subDay()->toDateString()]);

        $this->patchJson("/api/cotizaciones/{$id}/aprobar")->assertStatus(422);

        $otra = $this->cotizacion(['fecha_vencimiento' => now()->addDay()->toDateString()]);
        $this->patchJson("/api/cotizaciones/{$otra}/aprobar")->assertOk();
        DB::table('cotizaciones')->where('id', $otra)->update(['fecha_vencimiento' => now()->subDay()->toDateString()]);
        $this->postJson("/api/cotizaciones/{$otra}/convertir-venta")->assertStatus(422);

        $this->assertSame(0, Factura::count());
    }

    public function test_no_se_aprueba_una_cotizacion_sin_stock_suficiente(): void
    {
        $id = $this->cotizacion();
        $this->movimiento('Salida', 95)->assertCreated();

        $this->patchJson("/api/cotizaciones/{$id}/aprobar")->assertStatus(422);
    }

    public function test_si_el_stock_se_agota_despues_de_aprobar_no_se_convierte(): void
    {
        $id = $this->cotizacion();
        $this->patchJson("/api/cotizaciones/{$id}/aprobar")->assertOk();
        $this->movimiento('Salida', 95)->assertCreated();

        $this->postJson("/api/cotizaciones/{$id}/convertir-venta")->assertStatus(422);

        $this->assertSame(0, Factura::count());
        $this->assertSame('Aprobada', DB::table('cotizaciones')->where('id', $id)->value('estado'));
        $this->assertSame(5.0, $this->stock());
    }

    public function test_transiciones_de_cotizacion(): void
    {
        $id = $this->cotizacion();

        $this->postJson("/api/cotizaciones/{$id}/convertir-venta")->assertStatus(422);
        $this->patchJson("/api/cotizaciones/{$id}/rechazar", [])->assertStatus(422);
        $this->patchJson("/api/cotizaciones/{$id}/rechazar", ['motivo_rechazo' => 'El productor ya no necesita la semilla.'])->assertOk();
        $this->patchJson("/api/cotizaciones/{$id}/aprobar")->assertStatus(422);
    }

    // ------------------------------------------------------------------ reportes

    public function test_reportes_validan_fechas_y_montos(): void
    {
        $this->getJson('/api/reportes?tipo=ventas&fecha_inicio=2026-05-10&fecha_fin=2026-05-01')->assertStatus(422)->assertJsonValidationErrors('fecha_fin');
        $this->getJson('/api/reportes?tipo=ventas&fecha_inicio=2015-01-01&fecha_fin=2026-05-01')->assertStatus(422)->assertJsonValidationErrors('fecha_fin');
        $this->getJson('/api/reportes?tipo=ventas&monto_min=100&monto_max=10')->assertStatus(422)->assertJsonValidationErrors('monto_max');
        $this->getJson('/api/reportes?tipo=otro')->assertStatus(422)->assertJsonValidationErrors('tipo');
        $this->getJson('/api/reportes?tipo=ventas&fecha_inicio=2026-01-01&fecha_fin=2026-05-01')->assertOk();
    }

    // ------------------------------------------------------------------ permisos por rol

    public function test_permisos_por_rol_en_inventario_y_ventas(): void
    {
        foreach (['Técnico', 'Agricultor', 'Agrónomo'] as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);

            $this->movimiento('Entrada', 1, 'Compra')->assertForbidden();
            $this->postJson('/api/ventas', $this->documento())->assertForbidden();
            $this->postJson('/api/cotizaciones', $this->documento())->assertForbidden();
        }

        $this->assertSame(100.0, $this->stock());
    }
}
