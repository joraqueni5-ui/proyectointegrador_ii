<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioAgricola;
use Tests\TestCase;
use ZipArchive;

/**
 * Exportación del dataset validado: solo diagnósticos Confirmados y Corregidos, en carpetas por
 * clase FINAL (early_blight, healthy, late_blight) y con metadata.csv. Solo lectura.
 */
class DatasetValidadoTest extends TestCase
{
    use CreaEscenarioAgricola;
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->crearEscenarioAgricola();

        foreach (['CNN-TT', 'CNN-TTP', 'CNN-SANA', 'CNN-RZ'] as $codigo) {
            $this->ids[$codigo] = (int) DB::table('enfermedades')->where('codigo_enfermedad', $codigo)->value('id');
        }
    }

    private function diag(string $pred, string $estado, string $final, string $foto = 'ok', string $fecha = '2026-10-05'): int
    {
        $ruta = "diagnosticos/{$estado}_{$pred}_{$final}_".uniqid().'.jpg';

        if ($foto === 'ok') {
            Storage::disk('public')->put($ruta, 'contenido-de-una-foto');
        }

        return DB::table('diagnosticos_cnn')->insertGetId([
            'id_inspeccion' => $this->idInspeccion, 'id_enfermedad' => $this->ids[$final], 'ruta_fotografia' => $ruta,
            'porcentaje_confianza' => 91.5, 'modelo_version' => 'PROINPA-ResNet50-v2.1-prototipo',
            'top3_predicciones' => json_encode([['clase' => 'x', 'nombre' => 'x', 'codigo_enfermedad' => $pred, 'confianza' => 91.5]]),
            'estado_validacion' => $estado, 'created_at' => $fecha.' 09:00:00', 'updated_at' => $fecha.' 09:00:00',
        ]);
    }

    private function exportar(array $filtros = [], string $rol = 'Agrónomo')
    {
        Sanctum::actingAs($this->usuarios[$rol]);

        return $this->withHeaders(['Accept' => 'application/json'])->get('/api/diagnosticos/exportar-dataset?'.http_build_query($filtros));
    }

    /** Abre el ZIP descargado y devuelve [entradas, contenido del csv, contenido del LEEME]. */
    private function abrirZip($respuesta): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getRealPath()) === true, 'El ZIP no se pudo abrir');

        $entradas = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entradas[] = $zip->getNameIndex($i);
        }
        sort($entradas);

        $csv = $zip->getFromName('metadata.csv');
        $leeme = $zip->getFromName('LEEME.txt');
        $zip->close();

        return [$entradas, $csv, $leeme];
    }

    private function cargarCasos(): array
    {
        return [
            'tt_confirmado' => $this->diag('CNN-TT', 'Confirmado', 'CNN-TT'),          // late_blight
            'tt_corregido' => $this->diag('CNN-TT', 'Corregido', 'CNN-TTP'),            // early_blight
            'ttp_corregido' => $this->diag('CNN-TTP', 'Corregido', 'CNN-SANA'),         // healthy
            'sana_confirmado' => $this->diag('CNN-SANA', 'Confirmado', 'CNN-SANA'),     // healthy
            'rechazado' => $this->diag('CNN-TT', 'Rechazado', 'CNN-TT'),                // no se exporta
            'pendiente' => $this->diag('CNN-TT', 'Pendiente', 'CNN-TT'),                // no se exporta
            'otra_enfermedad' => $this->diag('CNN-TT', 'Corregido', 'CNN-RZ'),          // fuera de las 3 clases
            'sin_foto' => $this->diag('CNN-TT', 'Confirmado', 'CNN-TT', 'falta'),       // la foto no existe
        ];
    }

    public function test_el_zip_tiene_carpetas_por_clase_final_y_metadata(): void
    {
        $c = $this->cargarCasos();

        [$entradas] = $this->abrirZip($this->exportar()->assertOk());

        $esperadas = [
            sprintf('early_blight/diag_%05d.jpg', $c['tt_corregido']),
            sprintf('healthy/diag_%05d.jpg', $c['ttp_corregido']),
            sprintf('healthy/diag_%05d.jpg', $c['sana_confirmado']),
            sprintf('late_blight/diag_%05d.jpg', $c['tt_confirmado']),
            'LEEME.txt',
            'metadata.csv',
        ];
        sort($esperadas);

        $this->assertSame($esperadas, $entradas);
    }

    public function test_el_csv_tiene_las_columnas_y_los_datos_pedidos(): void
    {
        $c = $this->cargarCasos();

        [, $csv] = $this->abrirZip($this->exportar()->assertOk());

        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $filas = array_map('str_getcsv', array_filter(explode("\r\n", $csv)));
        $cabecera = array_shift($filas);

        $this->assertSame(['id_diagnostico', 'archivo', 'clase_predicha', 'clase_final', 'estado_validacion',
            'confianza', 'version_modelo', 'fecha', 'comunidad', 'parcela'], $cabecera);
        $this->assertCount(4, $filas);

        $porId = collect($filas)->mapWithKeys(fn ($f) => [(int) $f[0] => array_combine($cabecera, $f)]);

        // Corregido: la IA predijo tizón tardío y el agrónomo dejó tizón temprano
        $this->assertSame('late_blight', $porId[$c['tt_corregido']]['clase_predicha']);
        $this->assertSame('early_blight', $porId[$c['tt_corregido']]['clase_final']);
        $this->assertSame('Corregido', $porId[$c['tt_corregido']]['estado_validacion']);
        // Confirmado: predicha = final
        $this->assertSame('late_blight', $porId[$c['tt_confirmado']]['clase_final']);
        $this->assertSame('late_blight', $porId[$c['tt_confirmado']]['clase_predicha']);
        // Datos de contexto
        $this->assertSame('91.50', $porId[$c['tt_confirmado']]['confianza']);
        $this->assertSame('PROINPA-ResNet50-v2.1-prototipo', $porId[$c['tt_confirmado']]['version_modelo']);
        $this->assertSame('2026-10-05', $porId[$c['tt_confirmado']]['fecha']);
        $this->assertSame('Comunidad de prueba', $porId[$c['tt_confirmado']]['comunidad']);
        $this->assertSame('TEST-01', $porId[$c['tt_confirmado']]['parcela']);
    }

    public function test_el_leeme_cuenta_lo_que_no_se_pudo_incluir(): void
    {
        $this->cargarCasos();

        [, , $leeme] = $this->abrirZip($this->exportar()->assertOk());

        $this->assertStringContainsString('Fotos: 4', $leeme);
        $this->assertStringContainsString('late_blight/: 1', $leeme);
        $this->assertStringContainsString('healthy/: 2', $leeme);
        $this->assertStringContainsString('fuera de las 3 clases de la CNN: 1', $leeme);
        $this->assertStringContainsString('Foto no encontrada en el servidor: 1', $leeme);
    }

    public function test_filtra_por_fechas(): void
    {
        $this->diag('CNN-TT', 'Confirmado', 'CNN-TT', 'ok', '2026-08-01');
        $nuevo = $this->diag('CNN-TT', 'Confirmado', 'CNN-TT', 'ok', '2026-10-15');

        [$entradas] = $this->abrirZip($this->exportar(['fecha_desde' => '2026-10-01'])->assertOk());

        $this->assertContains(sprintf('late_blight/diag_%05d.jpg', $nuevo), $entradas);
        $this->assertCount(3, $entradas); // 1 foto + metadata.csv + LEEME.txt
    }

    public function test_sin_diagnosticos_validados_responde_con_un_mensaje_claro(): void
    {
        $this->diag('CNN-TT', 'Pendiente', 'CNN-TT');

        $this->exportar()->assertStatus(422)
            ->assertJsonPath('message', 'Todavía no hay diagnósticos confirmados o corregidos para exportar.');
    }

    public function test_si_ninguna_enfermedad_es_de_la_cnn_avisa(): void
    {
        $this->diag('CNN-TT', 'Corregido', 'CNN-RZ');

        $this->exportar()->assertStatus(422);
    }

    public function test_valida_los_filtros(): void
    {
        $this->cargarCasos();

        $this->exportar(['fecha_desde' => '2026-10-10', 'fecha_hasta' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_hasta');
        $this->exportar(['modelo_version' => 'no-existe'])
            ->assertStatus(422)->assertJsonValidationErrors('modelo_version');
    }

    public function test_solo_administrador_agronomo_y_superadmin_exportan(): void
    {
        $this->cargarCasos();

        foreach (['Técnico', 'Agricultor', 'Comercial', 'Encargado de almacén'] as $rol) {
            $this->exportar([], $rol)->assertForbidden();
        }

        foreach (['Agrónomo', 'Administrador', 'Superadmin'] as $rol) {
            $this->exportar([], $rol)->assertOk();
        }
    }

    public function test_exige_iniciar_sesion(): void
    {
        $this->get('/api/diagnosticos/exportar-dataset', ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_el_csv_no_permite_inyectar_formulas(): void
    {
        DB::table('comunidades')->where('id', $this->idComunidad)->update(['nombre_comunidad' => '=HYPERLINK("http://malo")']);
        $this->diag('CNN-TT', 'Confirmado', 'CNN-TT');

        [, $csv] = $this->abrirZip($this->exportar()->assertOk());

        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        $this->assertStringContainsString('"\'=HYPERLINK', $csv);
    }

    public function test_es_solo_lectura(): void
    {
        $this->cargarCasos();
        $antes = DB::table('diagnosticos_cnn')->get()->toArray();

        $this->exportar()->assertOk();

        $this->assertEquals($antes, DB::table('diagnosticos_cnn')->get()->toArray());
    }
}
