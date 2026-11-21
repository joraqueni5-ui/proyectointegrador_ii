<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos mínimos para las pruebas (solo existen en la base de pruebas):
 * roles reales (RolesSeeder), un usuario por rol y un cultivo activo con su inspección.
 */
trait CreaEscenarioAgricola
{
    /** @var array<string,User> */
    protected array $usuarios = [];

    protected int $idComunidad;
    protected int $idProductor;
    protected int $idParcela;
    protected int $idCultivo;
    protected int $idInspeccion;

    protected function crearRolesYUsuarios(): void
    {
        $this->seed(RolesSeeder::class);

        $n = 0;
        foreach (['Superadmin', 'Administrador', 'Agrónomo', 'Técnico', 'Agricultor', 'Comercial', 'Encargado de almacén'] as $nombre) {
            $n++;
            $this->usuarios[$nombre] = User::create([
                'name' => $nombre, 'paterno' => 'Prueba', 'materno' => 'Test',
                'email' => 'usuario'.$n.'@prueba.test', 'celular' => '7000000'.$n,
                'password' => Hash::make('Clave-Segura-123'),
                'id_rol' => DB::table('roles')->where('nombre', $nombre)->value('id'),
                'activo' => true,
            ]);
        }
    }

    protected function crearEscenarioAgricola(): void
    {
        $this->crearRolesYUsuarios();

        $this->idComunidad = DB::table('comunidades')->insertGetId([
            'nombre_comunidad' => 'Comunidad de prueba', 'municipio' => 'Batallas', 'provincia' => 'Los Andes',
            'departamento' => 'La Paz', 'latitud' => -16.3, 'longitud' => -68.5, 'altitud_msnm' => 3850,
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->idProductor = DB::table('productores')->insertGetId([
            'id_comunidad' => $this->idComunidad, 'nombre_completo' => 'Juan Mamani Quispe', 'nombres' => 'Juan',
            'apellido_paterno' => 'Mamani', 'apellido_materno' => 'Quispe', 'carnet_identidad' => '1234567',
            'ci_extension' => 'LP', 'telefono' => '71234567', 'sexo' => 'M', 'fecha_nacimiento' => '1980-05-10',
            'superficie_total_ha' => 5, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->idParcela = DB::table('parcelas')->insertGetId([
            'id_productor' => $this->idProductor, 'codigo_parcela' => 'TEST-01', 'nombre_referencial' => 'Parcela de prueba',
            'latitud' => -16.3, 'longitud' => -68.5, 'altitud_msnm' => 3850, 'superficie_ha' => 1.5,
            'tipo_suelo' => 'Franco', 'pendiente' => 'Plano', 'sistema_riego' => 'Secano', 'estado' => 'Activa',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->idCultivo = DB::table('cultivo_variedades')->insertGetId([
            'id_parcela' => $this->idParcela, 'variedad_papa' => 'Imilla Negra', 'fecha_siembra' => now()->subDays(60)->toDateString(),
            'cantidad_semilla_kg' => 2000, 'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->idInspeccion = $this->crearInspeccion();

        foreach ([['CNN-TT', 'Tizón Tardío', 'Critico'], ['CNN-TTP', 'Tizón Temprano', 'Medio'], ['CNN-SANA', 'Sana (Sin enfermedad)', 'Bajo']] as [$codigo, $nombre, $riesgo]) {
            if (DB::table('enfermedades')->where('codigo_enfermedad', $codigo)->exists()) {
                continue; // el catálogo base ya lo siembran las migraciones
            }

            DB::table('enfermedades')->insert([
                'nombre_comun' => $nombre, 'codigo_enfermedad' => $codigo, 'tipo' => 'Hongo', 'nivel_riesgo_base' => $riesgo,
                'medidas_control' => 'Medidas de control de prueba.', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function crearInspeccion(string $estado = 'En_proceso', ?int $idUsuario = null): int
    {
        return DB::table('inspecciones')->insertGetId([
            'id_usuario' => $idUsuario ?? $this->usuarios['Técnico']->id, 'id_parcela' => $this->idParcela,
            'id_cultivo' => $this->idCultivo, 'fecha_visita' => now()->toDateString(), 'hora_visita' => '10:00:00',
            'estado' => $estado, 'tipo_inspeccion' => 'Cultivo', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Respuesta simulada de /predict del servicio CNN. */
    protected function prediccionFalsa(string $codigo = 'CNN-TT', float $confianza = 91.2): array
    {
        return [
            'clase' => 'late_blight', 'nombre' => 'Tizón Tardío', 'codigo_enfermedad' => $codigo, 'confianza' => $confianza,
            'modelo_version' => 'PROINPA-ResNet50-v2.1-prototipo',
            'modelo' => ['version' => 'PROINPA-ResNet50-v2.1-prototipo', 'arquitectura' => 'ResNet50', 'calibrado' => true,
                'temperatura' => 0.58, 'confianza_minima' => 55, 'margen_minimo' => 8],
            'top3' => [
                ['clase' => 'late_blight', 'nombre' => 'Tizón Tardío', 'codigo_enfermedad' => 'CNN-TT', 'confianza' => $confianza],
                ['clase' => 'early_blight', 'nombre' => 'Tizón Temprano', 'codigo_enfermedad' => 'CNN-TTP', 'confianza' => 6.1],
                ['clase' => 'healthy', 'nombre' => 'Sana (Sin enfermedad)', 'codigo_enfermedad' => 'CNN-SANA', 'confianza' => 2.7],
            ],
            'calidad_imagen' => ['valida' => true, 'resolucion' => '640x480', 'brillo' => 120.5, 'contraste' => 48.2, 'nitidez' => 0.71],
            'validacion_contenido' => ['contenido_vegetal_probable' => true, 'vegetacion_pct' => 64.2],
            'validacion_nitidez' => ['nitidez_valida' => true, 'nitidez' => 0.71, 'umbral' => 0.42],
            'validacion_prediccion' => ['prediccion_confiable' => true, 'confianza_principal' => $confianza, 'segunda_confianza' => 6.1, 'margen' => round($confianza - 6.1, 2)],
        ];
    }
}
