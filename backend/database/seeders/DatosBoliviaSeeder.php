<?php

namespace Database\Seeders;

use App\Models\DiagnosticoCNN;
use App\Services\Diagnostico\DiagnosticoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionMethod;
use RuntimeException;

/**
 * Datos de ejemplo del altiplano boliviano para completar las tablas de negocio
 * (mínimo 20 registros por tabla). NO toca la tabla de usuarios: solo la lee
 * para referenciar usuarios existentes.
 *
 * Qué es real y qué es de ejemplo:
 *  - Reales: municipios/provincias de La Paz, variedades de papa, enfermedades y plagas
 *    del cultivo en los Andes, ingredientes activos y unidades de venta.
 *  - De ejemplo (inventados): personas, carnets, teléfonos, facturas, cotizaciones, stock,
 *    cosechas y precios (en Bs, referenciales). Coordenadas y altitudes son aproximadas.
 *
 * Ejecutar:   php artisan db:seed --class=DatosBoliviaSeeder
 * Simulación: DRY_RUN=1 php artisan db:seed --class=DatosBoliviaSeeder   (no guarda nada)
 * Es idempotente: si ya se ejecutó, no hace nada.
 */
class DatosBoliviaSeeder extends Seeder
{
    private const HOY = '2026-11-05';

    // Usuarios existentes (solo se referencian).
    private const TECNICOS = [2, 7, 10, 13, 19];
    private const AGRONOMOS = [4, 9, 12];
    private const ADMINS = [8, 3, 6];
    private const SUPERADMIN = [11, 17];
    private const ALMACEN = 14;
    private const COMERCIAL = 15;

    /** @var array<string,int> */
    private array $comunidad = [];
    /** @var int[] */
    private array $productoresNuevos = [];
    /** @var array<int,array<string,mixed>> */
    private array $parcelasNuevas = [];
    /** @var array<int,array<string,mixed>> */
    private array $etapas = [];
    /** @var array<int,array<string,mixed>> */
    private array $cultivos = [];
    /** @var array<string,int> */
    private array $prod = []; // codigo_producto => id
    /** @var array<int,array<string,mixed>> */
    private array $prodInfo = [];
    /** @var array<int,array<string,mixed>> */
    private array $almacenes = [];
    /** @var array<string,array<string,mixed>> */
    private array $plan = []; // "pid:aid" => inventario planificado
    /** @var array<string,float> */
    private array $resto = [];
    /** @var int[] */
    private array $todosProductores = [];
    private int $nroFactura = 8;
    /** @var string[] */
    private array $copias = [];

    public function run(): void
    {
        if (DB::table('comunidades')->where('nombre_comunidad', 'Ancoraimes')->exists()) {
            $this->command?->warn('DatosBoliviaSeeder ya se ejecutó: no se hace nada.');

            return;
        }

        mt_srand(2026);

        try {
            $this->ejecutar();
        } catch (\Throwable $e) {
            foreach ($this->copias as $f) {
                @unlink($f);
            }
            if (! str_starts_with($e->getMessage(), 'DRY_RUN')) {
                throw $e;
            }
            $this->command?->info('Simulación terminada: no se guardó nada.');

            return;
        }

        $this->command?->info('Datos de Bolivia cargados.');
    }

    private function ejecutar(): void
    {
        DB::transaction(function () {
            $this->comunidades();
            $this->etapas();
            $this->enfermedades();
            $this->productores();
            $this->parcelas();
            $this->cultivos();
            $this->cosechas();
            $this->productos();
            $this->almacenes();
            $this->planInventario();
            $this->ventasYCotizaciones();
            $this->inventarioYMovimientos();
            $this->recomendaciones();
            $this->inspeccionesDiagnosticosAlertas();

            if (getenv('DRY_RUN')) {
                throw new RuntimeException('DRY_RUN: se revierte todo.');
            }
        });
    }

    // ------------------------------------------------------------ utilidades
    private function r(int $a, int $b): int
    {
        return mt_rand($a, $b);
    }

    private function f(float $a, float $b, int $dec = 2): float
    {
        return round($a + (mt_rand() / mt_getrandmax()) * ($b - $a), $dec);
    }

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    /** Elección ponderada: ['Primera' => 45, 'Segunda' => 35, ...] */
    private function pesos(array $mapa): string
    {
        $total = array_sum($mapa);
        $n = mt_rand(1, $total);
        foreach ($mapa as $clave => $peso) {
            $n -= $peso;
            if ($n <= 0) {
                return (string) $clave;
            }
        }

        return (string) array_key_first($mapa);
    }

    private function ts(string $fecha, ?int $hora = null): string
    {
        return Carbon::parse($fecha)->setTime($hora ?? $this->r(7, 18), $this->r(0, 59), $this->r(0, 59))->format('Y-m-d H:i:s');
    }

    private function dia(string $desde, string $hasta): string
    {
        $a = Carbon::parse($desde);
        $b = Carbon::parse($hasta);

        return $a->copy()->addDays($this->r(0, max(0, (int) $a->diffInDays($b))))->format('Y-m-d');
    }

    private function telefono(): string
    {
        $n = $this->pick(['6', '7']).$this->r(1000000, 9999999);

        return $this->r(0, 2) === 0 ? "+591 {$n}" : $n;
    }

    private function ins(string $tabla, array $fila): int
    {
        return (int) DB::table($tabla)->insertGetId($fila);
    }

    // ------------------------------------------------------------ comunidades
    private function comunidades(): void
    {
        $filas = [
            ['Ancoraimes', 'Ancoraimes', 'Omasuyos', -15.9167, -68.8333, 3850],
            ['Carabuco', 'Carabuco', 'Camacho', -15.7667, -69.0667, 3840],
            ['Escoma', 'Escoma', 'Camacho', -15.6667, -69.1333, 3830],
            ['Puerto Pérez', 'Puerto Pérez', 'Los Andes', -16.3167, -68.6500, 3820],
            ['Ayo Ayo', 'Ayo Ayo', 'Aroma', -17.0833, -68.0000, 3860],
            ['Sica Sica', 'Sica Sica', 'Aroma', -17.3500, -67.7167, 3790],
            ['Desaguadero', 'Desaguadero', 'Ingavi', -16.5667, -69.0333, 3808],
        ];
        foreach ($filas as [$nom, $mun, $prov, $lat, $lon, $alt]) {
            $this->comunidad[$nom] = $this->ins('comunidades', [
                'nombre_comunidad' => $nom, 'municipio' => $mun, 'provincia' => $prov,
                'departamento' => 'La Paz', 'latitud' => $lat, 'longitud' => $lon,
                'altitud_msnm' => $alt, 'activo' => true,
                'created_at' => $this->ts('2026-06-02'), 'updated_at' => $this->ts('2026-06-02'),
            ]);
        }
        foreach (DB::table('comunidades')->get(['id', 'nombre_comunidad']) as $c) {
            $this->comunidad[$c->nombre_comunidad] ??= $c->id;
        }
    }

    // ------------------------------------------------------------ etapas fenológicas
    private function etapas(): void
    {
        $nuevas = [
            ['Inicio de brotación', 'BBCH-01', 3, 10, 'Los ojos del tubérculo-semilla comienzan a hincharse.'],
            ['Brote visible', 'BBCH-05', 8, 15, 'Brotes visibles de hasta 2 cm bajo el suelo.'],
            ['Primeras hojas desplegadas', 'BBCH-11', 20, 35, 'Primeras hojas verdaderas desplegadas sobre el suelo.'],
            ['Inicio de ramificación lateral', 'BBCH-21', 30, 45, 'Aparece el primer tallo lateral.'],
            ['Ramificación lateral completa', 'BBCH-29', 40, 55, 'Ramificación lateral completa; ideal para el aporque.'],
            ['Cobertura del surco 10 %', 'BBCH-31', 35, 50, 'Las hojas cubren el 10 % del surco.'],
            ['Cobertura del surco 50 %', 'BBCH-35', 45, 60, 'Las hojas cubren el 50 % del surco.'],
            ['Cobertura total del surco', 'BBCH-39', 55, 75, 'Cobertura máxima del follaje; mayor riesgo de tizón con humedad alta.'],
            ['Tubérculos al 50 % del tamaño final', 'BBCH-45', 65, 85, 'Los tubérculos alcanzan la mitad de su tamaño final.'],
            ['Botones florales visibles', 'BBCH-51', 50, 70, 'Se observan los primeros botones florales.'],
            ['Inicio de floración', 'BBCH-60', 60, 80, 'Primeras flores abiertas.'],
            ['Plena floración', 'BBCH-65', 70, 90, 'Mitad de las flores abiertas.'],
            ['Formación de frutos', 'BBCH-71', 80, 100, 'Aparecen los frutos verdes (bayas) en las variedades que fructifican.'],
            ['Inicio de maduración', 'BBCH-80', 90, 115, 'Inicio del amarillamiento del follaje.'],
            ['Senescencia del follaje', 'BBCH-91', 105, 135, 'Hojas amarillas y secas; la piel del tubérculo se fija.'],
        ];
        foreach ($nuevas as [$nom, $cod, $min, $max, $desc]) {
            $this->ins('etapas_fenologicas', [
                'nombre' => $nom, 'codigo' => $cod, 'dias_desde_siembra_min' => $min,
                'dias_desde_siembra_max' => $max, 'descripcion' => $desc, 'orden' => 99,
                'created_at' => $this->ts('2026-06-02'), 'updated_at' => $this->ts('2026-06-02'),
            ]);
        }
        // Reordena TODO el catálogo según la escala BBCH (orden biológico); solo cambia la columna "orden".
        $todas = DB::table('etapas_fenologicas')->get()->sortBy(fn ($e) => (int) substr($e->codigo, 5))->values();
        $orden = 1;
        foreach ($todas as $e) {
            DB::table('etapas_fenologicas')->where('id', $e->id)->update(['orden' => $orden++]);
            $this->etapas[$e->id] = (array) $e;
        }
    }

    private function etapaPorDias(int $dias): int
    {
        $mejor = null;
        $distancia = PHP_INT_MAX;
        foreach ($this->etapas as $e) {
            if ($e['codigo'] === 'BBCH-00' || $e['codigo'] === 'BBCH-99') {
                continue;
            }
            $centro = ($e['dias_desde_siembra_min'] + $e['dias_desde_siembra_max']) / 2;
            $d = abs($dias - $centro);
            if ($d < $distancia) {
                $distancia = $d;
                $mejor = (int) $e['id'];
            }
        }

        return $mejor;
    }

    // ------------------------------------------------------------ enfermedades y plagas
    private function enfermedades(): void
    {
        $filas = [
            ['Sarna común', 'Streptomyces scabies', 'ENF-SAR', 'Bacteria', 'Medio',
                'Lesiones corchosas, superficiales o profundas, de color pardo en la piel del tubérculo. No afecta el follaje.',
                'Enfermedad del suelo favorecida por suelos secos, alcalinos y pobres en materia orgánica. Afecta la calidad comercial del tubérculo.',
                'Usar semilla sana; mantener humedad del suelo durante la tuberización; incorporar materia orgánica; rotar con cereales o leguminosas; evitar el exceso de cal.'],
            ['Sarna polvorienta', 'Spongospora subterranea', 'ENF-SPO', 'Otro', 'Medio',
                'Pústulas pardas que se rompen y liberan un polvo oscuro en la piel del tubérculo; agallas en las raíces.',
                'Organismo del suelo favorecido por suelos fríos, húmedos y mal drenados.',
                'Rotación larga (4 a 5 años), mejorar el drenaje, usar semilla sana y evitar parcelas con historial de la enfermedad.'],
            ['Pierna negra y pudrición blanda', 'Pectobacterium carotovorum / Dickeya spp.', 'ENF-PLN', 'Bacteria', 'Alto',
                'Ennegrecimiento y pudrición húmeda de la base del tallo, marchitez de la planta y tubérculos con pudrición blanda de mal olor.',
                'Se favorece con suelos encharcados, heridas en la semilla y temperaturas altas durante el almacenamiento.',
                'Usar semilla certificada, evitar heridas y encharcamiento, eliminar plantas enfermas y almacenar los tubérculos secos y ventilados.'],
            ['Fusariosis (pudrición seca)', 'Fusarium solani / Fusarium oxysporum', 'ENF-FUS', 'Hongo', 'Medio',
                'Pudrición seca con arrugamiento en anillos concéntricos en el tubérculo; amarillamiento y marchitez de la planta.',
                'Infecta por heridas durante la cosecha y el manejo, y se desarrolla en el almacén.',
                'Evitar heridas al cosechar y manipular, curar los tubérculos antes de almacenar, tratar la semilla y rotar cultivos.'],
            ['Verticilosis', 'Verticillium dahliae', 'ENF-VER', 'Hongo', 'Medio',
                'Amarillamiento y marchitez progresiva desde las hojas inferiores, muerte prematura de la planta y oscurecimiento de los vasos.',
                'Hongo del suelo que persiste varios años y se agrava con estrés hídrico.',
                'Rotación con cereales, semilla sana, evitar estrés hídrico y eliminar los residuos de cosecha.'],
            ['Sarna plateada', 'Helminthosporium solani', 'ENF-SPL', 'Hongo', 'Bajo',
                'Manchas plateadas superficiales en la piel; el tubérculo se arruga durante el almacenamiento.',
                'Afecta la apariencia y la conservación, rara vez el rendimiento.',
                'Usar semilla sana, cosechar a tiempo y almacenar en lugar fresco y seco.'],
            ['Nematodo del quiste de la papa', 'Globodera pallida', 'ENF-NQP', 'Nematodo', 'Alto',
                'Plantas raquíticas y amarillentas en manchones, raíces con quistes de color blanquecino a pardo.',
                'Plaga de cuarentena en muchas regiones; los quistes sobreviven años en el suelo.',
                'Rotación larga con cultivos no hospederos (cebada, avena, haba), semilla certificada y evitar mover suelo entre parcelas.'],
            ['Falso nematodo del nudo', 'Nacobbus aberrans', 'ENF-NAC', 'Nematodo', 'Alto',
                'Agallas en las raíces en forma de rosario, plantas débiles y tubérculos deformes y pequeños.',
                'Importante en los Andes; tiene muchas malezas hospederas.',
                'Rotación con cereales, semilla sana, eliminar malezas hospederas y manejar el descanso del suelo.'],
            ['Virus X de la papa (PVX)', 'Potato virus X', 'ENF-PVX', 'Virus', 'Medio',
                'Mosaico leve y rugosidad de las hojas; muchas veces sin síntomas visibles. Reduce el rendimiento.',
                'Se transmite por contacto y por semilla infectada.',
                'Usar semilla certificada, desinfectar herramientas y eliminar plantas enfermas.'],
            ['Virus del enrollamiento de la hoja (PLRV)', 'Potato leafroll virus', 'ENF-PLR', 'Virus', 'Alto',
                'Enrollamiento de las hojas hacia arriba, textura coriácea y necrosis en red en el tubérculo.',
                'Lo transmiten los pulgones, principalmente Myzus persicae.',
                'Usar semilla certificada, controlar pulgones y eliminar las plantas con síntomas.'],
            ['Gorgojo de los Andes', 'Premnotrypes spp.', 'ENF-GOR', 'Otro', 'Alto',
                'Adultos que muerden el borde de las hojas; larvas que perforan galerías en los tubérculos.',
                'Principal plaga de la papa en el altiplano y los valles altos.',
                'Aporque alto, siembra temprana, recolección de adultos, rotación con cultivos no hospederos, almacenamiento limpio y control químico o biológico dirigido a adultos.'],
            ['Polilla de la papa', 'Phthorimaea operculella', 'ENF-POL', 'Otro', 'Alto',
                'Minas en hojas, galerías en tallos y tubérculos con excrementos.',
                'Más frecuente en zonas cálidas y secas y en almacén.',
                'Aporque, cosecha oportuna, no dejar tubérculos expuestos, almacén limpio y trampas con feromonas.'],
            ['Mosca minadora', 'Liriomyza huidobrensis', 'ENF-MIN', 'Otro', 'Medio',
                'Minas serpenteantes en las hojas y puntuaciones de alimentación.',
                'Se agrava con el uso de insecticidas de amplio espectro que eliminan a sus enemigos naturales.',
                'Monitoreo con trampas amarillas, conservar los enemigos naturales y evitar insecticidas de amplio espectro.'],
            ['Pulguilla de la papa', 'Epitrix spp.', 'ENF-PUL', 'Otro', 'Bajo',
                'Pequeños agujeros redondos en las hojas (aspecto de perdigonada).',
                'Rara vez requiere control; el daño es importante solo en plantas jóvenes.',
                'Monitoreo; controlar solo si la defoliación es fuerte.'],
            ['Daño por helada', null, 'ENF-HEL', 'Otro', 'Alto',
                'Hojas y tallos oscurecidos con aspecto de cocidos, marchitez súbita tras noches de helada.',
                'Fenómeno climático frecuente en el altiplano, sobre todo en siembras tempranas y tardías.',
                'Sembrar en fechas adecuadas, usar variedades tolerantes, fertilizar con potasio, regar antes de la helada y proteger con cortinas o ahumadas.'],
            ['Daño por granizo', null, 'ENF-GRA', 'Otro', 'Medio',
                'Hojas desgarradas y tallos con heridas; puede favorecer infecciones posteriores.',
                'Evento climático local e impredecible durante la época de lluvias.',
                'Registrar el daño, aplicar un fungicida protectante después del evento y fertilizar para favorecer la recuperación.'],
        ];
        foreach ($filas as [$nom, $cien, $cod, $tipo, $riesgo, $sint, $desc, $medidas]) {
            $this->ins('enfermedades', [
                'nombre_comun' => $nom, 'nombre_cientifico' => $cien, 'codigo_enfermedad' => $cod,
                'tipo' => $tipo, 'sintomas' => $sint, 'descripcion' => $desc, 'medidas_control' => $medidas,
                'nivel_riesgo_base' => $riesgo, 'activo' => true,
                'created_at' => $this->ts('2026-06-03'), 'updated_at' => $this->ts('2026-06-03'),
            ]);
        }
    }

    // ------------------------------------------------------------ productores
    private function productores(): void
    {
        $filas = [
            ['Eulogia', 'Condori', 'Apaza', 'F', '1976-03-12', 'Ancoraimes', 'Comunidad Ancoraimes, sector Pampa'],
            ['Basilio', 'Huanca', 'Mamani', 'M', '1969-07-21', 'Carabuco', 'Comunidad Carabuco, cerca de la plaza principal'],
            ['Justina', 'Callisaya', 'Quispe', 'F', '1983-11-02', 'Escoma', 'Comunidad Escoma, sector Santa Rosa'],
            ['Fortunato', 'Limachi', 'Choque', 'M', '1958-01-30', 'Puerto Pérez', 'Puerto Pérez, camino a la ribera del lago'],
            ['Dominga', 'Catari', 'Ticona', 'F', '1990-05-17', 'Ayo Ayo', 'Ayo Ayo, zona norte'],
            ['Germán', 'Nina', 'Colque', 'M', '1987-09-09', 'Sica Sica', 'Sica Sica, sector Ladera'],
            ['Natividad', 'Yujra', 'Poma', 'F', '1972-12-24', 'Desaguadero', 'Desaguadero, zona fronteriza'],
            ['Eusebio', 'Alanoca', 'Copa', 'M', '1965-04-03', 'Guaqui', 'Guaqui, barrio San Pedro'],
            ['Genoveva', 'Chambi', 'Flores', 'F', '1980-08-15', 'Tihuanaco', 'Tiwanaku, sector Lakaya'],
            ['Cipriano', 'Villca', 'Laura', 'M', '1974-02-28', 'Calamarca', 'Calamarca, comunidad originaria'],
            ['Feliciana', 'Vargas', 'Ramos', 'F', '1993-06-06', 'Alto Peñas', 'Batallas, sector Alto Peñas'],
            ['Marcelino', 'Gutiérrez', 'Cruz', 'M', '1962-10-19', 'Umala Centro', 'Umala, calle principal s/n'],
        ];
        $usados = DB::table('productores')->pluck('carnet_identidad')->all();
        foreach ($filas as [$nom, $ap, $am, $sexo, $nac, $com, $dir]) {
            do {
                $ci = (string) $this->r(2000000, 9999999);
            } while (in_array($ci, $usados, true));
            $usados[] = $ci;
            $this->productoresNuevos[] = $this->ins('productores', [
                'id_comunidad' => $this->comunidad[$com], 'nombre_completo' => "{$nom} {$ap} {$am}",
                'nombres' => $nom, 'apellido_paterno' => $ap, 'apellido_materno' => $am,
                'carnet_identidad' => $ci, 'ci_extension' => 'LP', 'complemento_ci' => null,
                'telefono' => $this->telefono(), 'sexo' => $sexo, 'fecha_nacimiento' => $nac,
                'direccion' => $dir, 'superficie_total_ha' => 0, 'observaciones' => null, 'activo' => true,
                'created_at' => $this->ts('2026-06-05'), 'updated_at' => $this->ts('2026-06-05'),
            ]);
        }
    }

    // ------------------------------------------------------------ parcelas
    private function parcelas(): void
    {
        $nombresRef = ['Aynuqa Qullpa', 'Pampa Jawira', 'Ladera Santa Cruz', 'Lote Cruz Pata', 'Aynuqa Central',
            'Pampa Grande', 'Chacra Wiñay', 'Ladera Orqo', 'Lote Kollpa', 'Parcela Qhuthaña', 'Pampa Alta', 'Lote Jach\'a Uma'];
        $suelos = ['Franco', 'Franco-arcilloso', 'Arenoso', 'Limoso', 'Franco', 'Arcilloso'];
        $pendientes = ['Plano', 'Suave', 'Suave', 'Moderado', 'Plano', 'Fuerte'];
        $abrev = ['Ancoraimes' => 'ANC', 'Carabuco' => 'CAR', 'Escoma' => 'ESC', 'Puerto Pérez' => 'PPE', 'Ayo Ayo' => 'AYO',
            'Sica Sica' => 'SIC', 'Desaguadero' => 'DES', 'Guaqui' => 'GUA', 'Tihuanaco' => 'TIW', 'Calamarca' => 'CAL',
            'Alto Peñas' => 'APE', 'Umala Centro' => 'UMA'];
        $productores = DB::table('productores')->whereIn('id', $this->productoresNuevos)->get()->keyBy('id');
        $comunidades = DB::table('comunidades')->get()->keyBy('id');

        // 12 productores con 1 parcela + 4 con una segunda parcela
        $asignacion = array_merge($this->productoresNuevos, [
            $this->productoresNuevos[0], $this->productoresNuevos[1], $this->productoresNuevos[4], $this->productoresNuevos[8],
        ]);
        $contador = [];
        foreach ($asignacion as $i => $idProductor) {
            $p = $productores[$idProductor];
            $c = $comunidades[$p->id_comunidad];
            $pref = $abrev[$c->nombre_comunidad] ?? strtoupper(substr($c->nombre_comunidad, 0, 3));
            $contador[$pref] = ($contador[$pref] ?? 0) + 1;
            $riego = $this->pesos(['Secano' => 75, 'Riego' => 12, 'Mixto' => 13]);
            $sup = $this->f(0.35, 2.4);
            $estado = $i === 14 ? 'En_descanso' : ($i === 15 ? 'Inactiva' : 'Activa');
            $id = $this->ins('parcelas', [
                'id_productor' => $idProductor,
                'codigo_parcela' => sprintf('%s-%02d', $pref, $contador[$pref]),
                'nombre_referencial' => $nombresRef[$i % count($nombresRef)],
                'latitud' => round($c->latitud + $this->f(-0.012, 0.012, 6), 6),
                'longitud' => round($c->longitud + $this->f(-0.012, 0.012, 6), 6),
                'altitud_msnm' => $c->altitud_msnm + $this->r(-25, 25),
                'superficie_ha' => $sup,
                'tipo_suelo' => $suelos[$i % count($suelos)],
                'pendiente' => $pendientes[$i % count($pendientes)],
                'sistema_riego' => $riego, 'estado' => $estado,
                'observaciones' => $riego === 'Secano' ? 'Cultivo a secano; depende de las lluvias de la campaña.' : null,
                'created_at' => $this->ts('2026-06-08'), 'updated_at' => $this->ts('2026-06-08'),
            ]);
            $this->parcelasNuevas[] = ['id' => $id, 'productor' => $idProductor, 'sup' => $sup, 'estado' => $estado];
        }
        foreach ($this->productoresNuevos as $idProductor) {
            $suma = collect($this->parcelasNuevas)->where('productor', $idProductor)->sum('sup');
            DB::table('productores')->where('id', $idProductor)->update(['superficie_total_ha' => round($suma + $this->f(0, 0.6), 2)]);
        }
    }

    // ------------------------------------------------------------ cultivos
    private function cultivos(): void
    {
        $variedades = ['Imilla Negra', 'Imilla Blanca', 'Huaycha', 'Desiree', 'Runa', 'Sani Imilla', 'Pinta Boca', 'Alpha', 'Ajanhuiri', 'Luk\'i', 'Yungay'];
        $procedencia = ['Propia', 'Comunidad', 'Certificada', 'Feria local', 'Certificada', 'Propia'];
        $np = $this->parcelasNuevas;

        // [parcela, temporada, estado]
        $plan = [];
        for ($i = 0; $i < 10; $i++) {
            $plan[] = [$np[$i], '2526', 'Cosechado'];
        }
        foreach ([4, 12, 14, 2] as $existente) {
            $sup = (float) DB::table('parcelas')->where('id', $existente)->value('superficie_ha');
            $plan[] = [['id' => $existente, 'sup' => $sup], '2425', 'Cosechado'];
        }
        for ($i = 10; $i < 14; $i++) {
            $plan[] = [$np[$i], '2627', 'Activo'];
        }
        $plan[] = [$np[14], '2526', 'Perdido'];
        $plan[] = [$np[15], '2526', 'Abandonado'];

        foreach ($plan as $k => [$parcela, $temporada, $estado]) {
            [$a, $b] = match ($temporada) {
                '2425' => ['2024-10-10', '2024-11-25'],
                '2526' => ['2025-10-12', '2025-11-25'],
                default => ['2026-09-08', '2026-10-28'],
            };
            $siembra = $this->dia($a, $b);
            $est = Carbon::parse($siembra)->addDays($this->r(165, 205))->format('Y-m-d');
            $dias = (int) Carbon::parse($siembra)->diffInDays(self::HOY);
            $etapa = match ($estado) {
                'Cosechado' => (int) DB::table('etapas_fenologicas')->where('codigo', 'BBCH-99')->value('id'),
                'Activo' => $this->etapaPorDias($dias),
                'Perdido' => $this->etapaPorDias(75),
                default => $this->etapaPorDias(30),
            };
            $variedad = $variedades[$k % count($variedades)];
            $obs = match ($estado) {
                'Perdido' => 'Pérdida casi total por helada y sequía durante la campaña.',
                'Abandonado' => 'Cultivo abandonado por falta de mano de obra en la campaña.',
                'Activo' => 'Siembra de la campaña 2026/27.',
                default => null,
            };
            $id = $this->ins('cultivo_variedades', [
                'id_parcela' => $parcela['id'], 'variedad_papa' => $variedad, 'fecha_siembra' => $siembra,
                'cantidad_semilla_kg' => round($parcela['sup'] * $this->r(1500, 2200) / 5) * 5,
                'procedencia_semilla' => $procedencia[$k % count($procedencia)],
                'id_etapa_fenologica' => $etapa, 'fecha_estimada_cosecha' => $est, 'observaciones' => $obs, 'estado' => $estado,
                'created_at' => $this->ts($siembra), 'updated_at' => $this->ts($siembra),
            ]);
            $this->cultivos[] = ['id' => $id, 'parcela' => $parcela['id'], 'sup' => $parcela['sup'], 'siembra' => $siembra,
                'est' => $est, 'estado' => $estado, 'variedad' => $variedad, 'temporada' => $temporada, 'k' => $k];
        }
    }

    // ------------------------------------------------------------ cosechas
    private function cosechas(): void
    {
        $usuarios = array_merge(self::TECNICOS, self::AGRONOMOS, [8]);
        $precioBase = ['Primera' => 150, 'Segunda' => 115, 'Tercera' => 80, 'Descarte' => 40];
        foreach ($this->cultivos as $c) {
            if ($c['estado'] !== 'Cosechado') {
                continue;
            }
            $fecha = Carbon::parse($c['est'])->addDays($this->r(-10, 12))->format('Y-m-d');
            $rend = $this->f(5.5, 14.5, 3);
            $ton = round($rend * $c['sup'], 3);
            $calidad = $this->pesos(['Primera' => 45, 'Segunda' => 35, 'Tercera' => 15, 'Descarte' => 5]);
            $destino = $this->pesos(['Venta' => 50, 'Mixto' => 25, 'Semilla' => 15, 'Autoconsumo' => 10]);
            $precio = $destino === 'Autoconsumo' ? null : round($precioBase[$calidad] + $this->r(-15, 25), 2);
            $this->ins('cosechas', [
                'id_parcela' => $c['parcela'], 'id_cultivo' => $c['id'], 'id_usuario' => $this->pick($usuarios),
                'fecha_cosecha' => $fecha, 'cantidad_toneladas' => $ton,
                'rendimiento_t_ha' => round($ton / $c['sup'], 3), 'calidad' => $calidad, 'destino' => $destino,
                'precio_unitario_bs' => $precio,
                'ingreso_estimado_bs' => $precio === null ? null : round($ton * 10 * $precio, 2),
                'observaciones' => $this->pick([null, null, 'Cosecha manual con apoyo familiar.', 'Parte de la producción se destinó a chuño.', 'Buen calibre de tubérculo.']),
                'created_at' => $this->ts($fecha), 'updated_at' => $this->ts($fecha), 'deleted_at' => null,
            ]);
        }
    }

    // ------------------------------------------------------------ productos
    private function productos(): void
    {
        // código, nombre, categoría, unidad, precio Bs, costo Bs, stock mínimo, descripción
        $filas = [
            ['S-005', 'Semilla certificada Imilla Negra', 'Semilla', 'kg', 8.00, 6.50, 200, 'Semilla certificada de papa Imilla Negra, calibre mediano.'],
            ['S-006', 'Semilla certificada Huaycha', 'Semilla', 'kg', 7.50, 6.00, 200, 'Semilla certificada de papa Huaycha (Waych\'a).'],
            ['S-007', 'Semilla certificada Runa', 'Semilla', 'kg', 8.50, 7.00, 150, 'Semilla certificada de papa Runa.'],
            ['S-008', 'Semilla certificada Sani Imilla', 'Semilla', 'kg', 7.50, 6.00, 150, 'Semilla certificada de papa Sani Imilla.'],
            ['FER-UREA-46', 'Urea 46 % N', 'Fertilizante', 'bolsa 50 kg', 185.00, 160.00, 30, 'Fertilizante nitrogenado granulado.'],
            ['FER-DAP', 'Fosfato diamónico 18-46-0', 'Fertilizante', 'bolsa 50 kg', 340.00, 300.00, 30, 'Fertilizante fosfatado para fondo de siembra.'],
            ['FER-SPK', 'Sulfato de potasio', 'Fertilizante', 'bolsa 50 kg', 390.00, 340.00, 20, 'Fuente de potasio y azufre; mejora la calidad del tubérculo.'],
            ['FER-HUM', 'Humus de lombriz', 'Fertilizante', 'bolsa 25 kg', 65.00, 50.00, 40, 'Abono orgánico para mejorar el suelo.'],
            ['AGQ-MAN-80', 'Mancozeb 80 % WP', 'Agroquimico', 'kg', 78.00, 62.00, 40, 'Fungicida de contacto protectante.'],
            ['AGQ-MET-MZ', 'Metalaxil-M + Mancozeb 68 % WP', 'Agroquimico', 'kg', 195.00, 160.00, 20, 'Fungicida sistémico y de contacto contra tizón tardío.'],
            ['AGQ-CLO-72', 'Clorotalonil 72 % SC', 'Agroquimico', 'litros', 88.00, 70.00, 25, 'Fungicida de contacto de amplio espectro.'],
            ['AGQ-CIM-MZ', 'Cimoxanil + Mancozeb 72 % WP', 'Agroquimico', 'kg', 140.00, 115.00, 20, 'Fungicida curativo y protectante contra tizón.'],
            ['AGQ-CIP-25', 'Cipermetrina 25 % EC', 'Agroquimico', 'litros', 72.00, 58.00, 25, 'Insecticida piretroide para gorgojo y polilla.'],
            ['AGQ-IMI-35', 'Imidacloprid 35 % SC', 'Agroquimico', 'litros', 165.00, 135.00, 15, 'Insecticida sistémico contra pulgones y minadores.'],
            ['AGQ-CAR-50', 'Carbendazim 50 % SC', 'Agroquimico', 'litros', 92.00, 75.00, 20, 'Fungicida sistémico para tratamiento de semilla.'],
            ['BIO-TRI', 'Trichoderma harzianum', 'Bioinsumo', 'kg', 125.00, 95.00, 15, 'Hongo benéfico para el control biológico de hongos del suelo.'],
            ['BIO-BAC', 'Bacillus subtilis', 'Bioinsumo', 'litros', 115.00, 90.00, 15, 'Bacteria benéfica para el control biológico de patógenos.'],
            ['BIO-BEA', 'Beauveria bassiana', 'Bioinsumo', 'kg', 135.00, 105.00, 10, 'Hongo entomopatógeno para el control de gorgojos y polillas.'],
            ['EQ-MOCH', 'Mochila fumigadora 20 L', 'Equipo', 'unidad', 320.00, 260.00, 5, 'Mochila manual para aplicación de fitosanitarios.'],
            ['EQ-EPP', 'Kit de protección personal para aplicación', 'Equipo', 'unidad', 185.00, 140.00, 8, 'Mascarilla, guantes, lentes y traje para aplicar agroquímicos.'],
        ];
        foreach ($filas as [$cod, $nom, $cat, $uni, $precio, $costo, $min, $desc]) {
            $id = $this->ins('productos', [
                'codigo_producto' => $cod, 'nombre_producto' => $nom, 'categoria' => $cat, 'descripcion' => $desc,
                'unidad_medida' => $uni, 'precio_unitario' => $precio, 'precio_costo' => $costo, 'stock_minimo' => $min,
                'imagen_url' => null, 'activo' => true, 'id_usuario_registro' => self::ALMACEN, 'id_usuario_actualizacion' => null,
                'created_at' => $this->ts('2026-07-01'), 'updated_at' => $this->ts('2026-07-01'),
            ]);
            $this->prod[$cod] = $id;
            $this->prodInfo[$id] = ['cod' => $cod, 'precio' => $precio, 'min' => $min, 'cat' => $cat, 'unidad' => $uni];
        }
    }

    // ------------------------------------------------------------ almacenes
    private function almacenes(): void
    {
        $filas = [
            ['Centro de Acopio El Alto', 'Zona Ciudad Satélite, El Alto', 'La Paz', 14, 600, 'Activo'],
            ['Almacén Viacha', 'Zona industrial, Viacha', 'La Paz', 3, 450, 'Activo'],
            ['Centro de Acopio Batallas', 'Plaza principal de Batallas', 'La Paz', 14, 300, 'Activo'],
            ['Depósito Achacachi', 'Avenida Bolívar, Achacachi', 'La Paz', 3, 280, 'Activo'],
            ['Depósito Pucarani', 'Calle Sucre, Pucarani', 'La Paz', 14, 220, 'Activo'],
            ['Almacén Patacamaya', 'Carretera La Paz - Oruro, Patacamaya', 'La Paz', 11, 400, 'Activo'],
            ['Depósito Tiwanaku', 'Zona de acceso al sitio arqueológico, Tiwanaku', 'La Paz', 3, 180, 'Activo'],
            ['Depósito Laja', 'Plaza principal de Laja', 'La Paz', 14, 150, 'Activo'],
            ['Depósito Calamarca', 'Calle principal, Calamarca', 'La Paz', 11, 160, 'Activo'],
            ['Depósito Sica Sica', 'Carretera a Oruro, Sica Sica', 'La Paz', 3, 200, 'Activo'],
            ['Depósito Ayo Ayo', 'Plaza de Ayo Ayo', 'La Paz', 14, 120, 'En_mantenimiento'],
            ['Depósito Desaguadero', 'Zona fronteriza, Desaguadero', 'La Paz', 11, 250, 'Activo'],
            ['Depósito Guaqui', 'Puerto de Guaqui', 'La Paz', 3, 140, 'Activo'],
            ['Depósito Ancoraimes', 'Plaza principal de Ancoraimes', 'La Paz', 14, 130, 'Activo'],
            ['Depósito Carabuco', 'Calle Murillo, Carabuco', 'La Paz', 11, 110, 'Inactivo'],
            ['Centro de Acopio Tiraque', 'Zona de ferias, Tiraque', 'Cochabamba', 3, 350, 'Activo'],
            ['Centro de Acopio Colomi', 'Carretera Cochabamba - Villa Tunari, Colomi', 'Cochabamba', 14, 320, 'Activo'],
            ['Almacén Betanzos', 'Plaza principal de Betanzos', 'Potosí', 11, 260, 'Activo'],
            ['Almacén Challapata', 'Avenida Ferroviaria, Challapata', 'Oruro', 3, 240, 'Activo'],
            ['Depósito Escoma', 'Plaza principal de Escoma', 'La Paz', 14, 100, 'Activo'],
        ];
        foreach ($filas as $i => [$nom, $ubi, $dep, $resp, $cap, $estado]) {
            $id = $this->ins('almacenes', [
                'nombre_almacen' => $nom, 'ubicacion' => $ubi, 'departamento' => $dep, 'id_responsable' => $resp,
                'capacidad_maxima_m3' => $cap, 'telefono_contacto' => (string) ($this->pick(['6', '7']).$this->r(1000000, 9999999)),
                'observaciones' => $estado === 'En_mantenimiento' ? 'Reparación del techo antes de la campaña.' : null,
                'estado' => $estado, 'created_at' => $this->ts('2026-06-20'), 'updated_at' => $this->ts('2026-06-20'),
            ]);
            $this->almacenes[$id] = ['id' => $id, 'resp' => $resp, 'estado' => $estado, 'central' => $i < 6, 'dep' => $dep];
        }
    }

    // ------------------------------------------------------------ plan de inventario (en memoria)
    private function planInventario(): void
    {
        $ids = array_keys($this->prodInfo);
        foreach ($this->almacenes as $aid => $a) {
            $n = $a['central'] ? $this->r(8, 11) : $this->r(2, 4);
            $claves = (array) array_rand(array_flip($ids), $n);
            foreach ($claves as $pid) {
                $info = $this->prodInfo[$pid];
                $inicial = match ($info['cat']) {
                    'Semilla' => $this->r(400, 1800),
                    'Fertilizante' => $this->r(60, 320),
                    'Equipo' => $this->r(8, 30),
                    default => $this->r(40, 200),
                };
                $this->plan["$pid:$aid"] = [
                    'pid' => $pid, 'aid' => $aid, 'inicial' => $inicial, 'fecha' => $this->dia('2026-07-01', '2026-08-25'),
                    'eventos' => [],
                ];
                $this->resto["$pid:$aid"] = $inicial;
            }
        }
    }

    // ------------------------------------------------------------ ventas y cotizaciones
    /** Elige un almacén con inventario y arma líneas de venta (1 a 3 productos) respetando el stock. */
    private function lineasPara(): array
    {
        $conStock = [];
        foreach ($this->plan as $k => $p) {
            $conStock[$p['aid']][] = $k;
        }
        $aids = array_filter(array_keys($conStock), fn ($a) => $this->almacenes[$a]['estado'] === 'Activo' && count($conStock[$a]) >= 2);
        $aid = $this->pick(array_values($aids));
        $claves = $conStock[$aid];
        shuffle($claves);
        $lineas = [];
        foreach (array_slice($claves, 0, $this->r(1, 3)) as $k) {
            $p = $this->plan[$k];
            $info = $this->prodInfo[$p['pid']];
            $max = (int) floor($this->resto[$k] * 0.3);
            $tope = match ($info['cat']) {
                'Semilla' => 250, 'Fertilizante' => 14, 'Equipo' => 3, default => 12,
            };
            $cant = min($max, $this->r(1, $tope));
            if ($cant < 1) {
                continue;
            }
            $lineas[] = ['pid' => $p['pid'], 'aid' => $aid, 'cant' => $cant, 'precio' => $info['precio'], 'clave' => $k];
        }

        return $lineas ?: $this->lineasPara();
    }

    private function totales(array $lineas, float $descGlobal): array
    {
        $sub = 0.0;
        foreach ($lineas as $l) {
            $sub += round($l['cant'] * $l['precio'], 2);
        }

        return [round($sub, 2), round($descGlobal, 2), round($sub - $descGlobal, 2)];
    }

    private function crearFactura(array $lineas, int $productor, int $usuario, string $fecha, string $metodo, string $estado, float $desc, ?string $obs): int
    {
        [$sub, $d, $total] = $this->totales($lineas, $desc);
        $this->nroFactura++;
        $id = $this->ins('facturas', [
            'id_productor' => $productor, 'id_usuario' => $usuario, 'nro_factura' => sprintf('%04d', $this->nroFactura),
            'tipo' => 'Venta', 'fecha_emision' => $fecha,
            'fecha_vencimiento' => $metodo === 'Credito' ? Carbon::parse($fecha)->addDays(30)->format('Y-m-d') : null,
            'subtotal' => $sub, 'descuento' => $d, 'monto_total' => $total, 'metodo_pago' => $metodo, 'estado' => $estado,
            'observaciones' => $obs, 'created_at' => $this->ts($fecha), 'updated_at' => $this->ts($fecha),
        ]);
        foreach ($lineas as $l) {
            $this->ins('detalle_facturas', [
                'id_factura' => $id, 'id_producto' => $l['pid'], 'cantidad' => $l['cant'], 'precio_unitario' => $l['precio'],
                'descuento_linea' => 0, 'subtotal' => round($l['cant'] * $l['precio'], 2),
                'created_at' => $this->ts($fecha), 'updated_at' => $this->ts($fecha),
            ]);
            $this->resto[$l['clave']] -= $l['cant'];
            $this->plan[$l['clave']]['eventos'][] = [
                'fecha' => $fecha, 'tipo' => 'Salida', 'cant' => $l['cant'], 'motivo' => 'Venta', 'factura' => $id, 'usuario' => $usuario, 'obs' => null,
            ];
            if ($estado === 'Anulada') {
                $dev = Carbon::parse($fecha)->addDay()->format('Y-m-d');
                $this->resto[$l['clave']] += $l['cant'];
                $this->plan[$l['clave']]['eventos'][] = [
                    'fecha' => $dev, 'tipo' => 'Entrada', 'cant' => $l['cant'], 'motivo' => 'Devolucion', 'factura' => $id,
                    'usuario' => $usuario, 'obs' => 'Venta anulada: el producto regresa al inventario.',
                ];
            }
        }

        return $id;
    }

    private function ventasYCotizaciones(): void
    {
        $this->todosProductores = DB::table('productores')->where('activo', true)->pluck('id')->all();
        $vendedores = array_merge([self::COMERCIAL, self::COMERCIAL, self::COMERCIAL], self::ADMINS, self::SUPERADMIN);
        $metodos = ['Efectivo' => 40, 'Transferencia' => 25, 'QR' => 25, 'Credito' => 10];

        // 17 cotizaciones: 5 se convierten en venta
        $estados = array_merge(array_fill(0, 5, 'Convertida'), array_fill(0, 5, 'Pendiente'), array_fill(0, 3, 'Aprobada'),
            array_fill(0, 2, 'Rechazada'), array_fill(0, 2, 'Anulada'));
        $nroCot = 4;
        $fechas = [];
        foreach ($estados as $e) {
            $fechas[] = $this->dia('2026-08-28', '2026-11-03');
        }
        sort($fechas);
        $facturasDirectas = [];
        foreach ($estados as $i => $estado) {
            $fecha = $fechas[$i];
            $lineas = $this->lineasPara();
            $productor = $this->pick($this->todosProductores);
            $usuario = $this->pick($vendedores);
            $metodo = $this->pesos($metodos);
            [$sub, , $total] = $this->totales($lineas, 0);
            $nroCot++;
            $aprobador = in_array($estado, ['Aprobada', 'Convertida'], true) ? $this->pick(array_merge(self::ADMINS, self::SUPERADMIN)) : null;
            $facturaId = null;
            if ($estado === 'Convertida') {
                $fechaFactura = Carbon::parse($fecha)->addDays($this->r(1, 5))->format('Y-m-d');
                $facturaId = $this->crearFactura($lineas, $productor, $usuario, $fechaFactura, $metodo,
                    $metodo === 'Credito' ? 'Pendiente' : 'Pagada', 0, sprintf('Venta generada desde cotización #COT-%04d', $nroCot));
            }
            $cotId = $this->ins('cotizaciones', [
                'nro_cotizacion' => sprintf('COT-%04d', $nroCot), 'id_productor' => $productor, 'id_usuario' => $usuario,
                'id_aprobador' => $aprobador, 'convertida_factura_id' => $facturaId, 'fecha_emision' => $fecha,
                'fecha_vencimiento' => Carbon::parse($fecha)->addDays(15)->format('Y-m-d'),
                'subtotal' => $sub, 'descuento' => 0, 'monto_total' => $total, 'metodo_pago' => $metodo, 'estado' => $estado,
                'observaciones' => $this->pick([null, 'Entrega en la comunidad.', 'Cliente solicita factura.', null]),
                'motivo_rechazo' => $estado === 'Rechazada' ? $this->pick(['El productor no confirmó el pedido.', 'Precio fuera del presupuesto del productor.']) : null,
                'aprobada_at' => $aprobador ? $this->ts(Carbon::parse($fecha)->addDay()->format('Y-m-d')) : null,
                'rechazada_at' => $estado === 'Rechazada' ? $this->ts(Carbon::parse($fecha)->addDays(2)->format('Y-m-d')) : null,
                'convertida_at' => $facturaId ? $this->ts(Carbon::parse($fecha)->addDays(3)->format('Y-m-d')) : null,
                'created_at' => $this->ts($fecha), 'updated_at' => $this->ts($fecha),
            ]);
            foreach ($lineas as $l) {
                $this->ins('detalle_cotizaciones', [
                    'id_cotizacion' => $cotId, 'id_producto' => $l['pid'], 'id_almacen' => $l['aid'], 'cantidad' => $l['cant'],
                    'precio_unitario' => $l['precio'], 'descuento_linea' => 0, 'subtotal' => round($l['cant'] * $l['precio'], 2),
                    'created_at' => $this->ts($fecha), 'updated_at' => $this->ts($fecha),
                ]);
            }
        }

        // 11 ventas directas (una anulada, una con pago parcial, tres a crédito)
        $directas = [];
        for ($i = 0; $i < 11; $i++) {
            $directas[] = $this->dia('2026-08-25', '2026-11-04');
        }
        sort($directas);
        foreach ($directas as $i => $fecha) {
            $metodo = $i === 3 || $i === 6 || $i === 9 ? 'Credito' : $this->pesos(['Efectivo' => 40, 'Transferencia' => 25, 'QR' => 35]);
            $estado = match (true) {
                $i === 9 => 'Pendiente',
                $i === 6 => 'Parcial',
                $i === 3 => 'Pagada',
                $i === 5 => 'Anulada',
                default => 'Pagada',
            };
            $lineas = $this->lineasPara();
            [$sub] = $this->totales($lineas, 0);
            $desc = $sub >= 600 && $i % 2 === 0 ? round($sub * 0.05, 2) : 0.0;
            $this->crearFactura($lineas, $this->pick($this->todosProductores), $this->pick($vendedores), $fecha, $metodo, $estado, $desc,
                $estado === 'Anulada' ? 'Anulada por error en la cantidad solicitada.' : null);
        }
    }

    // ------------------------------------------------------------ inventario y movimientos
    private function inventarioYMovimientos(): void
    {
        // 3 traslados entre almacenes (mismo producto)
        $claves = array_keys($this->plan);
        shuffle($claves);
        $hechos = 0;
        foreach ($claves as $k) {
            if ($hechos >= 3) {
                break;
            }
            $p = $this->plan[$k];
            if ($this->almacenes[$p['aid']]['central'] === false || $this->resto[$k] < 150) {
                continue;
            }
            $destinoAid = array_keys(array_filter($this->almacenes, fn ($a) => ! $a['central'] && $a['estado'] === 'Activo' && ! isset($this->plan["{$p['pid']}:{$a['id']}"])));
            if (! $destinoAid) {
                continue;
            }
            $dest = $this->pick($destinoAid);
            $cant = (int) floor($this->resto[$k] * 0.2);
            $fecha = $this->dia('2026-09-10', '2026-10-20');
            $kd = "{$p['pid']}:$dest";
            $this->plan[$kd] = ['pid' => $p['pid'], 'aid' => $dest, 'inicial' => 0, 'fecha' => $fecha, 'eventos' => []];
            $this->resto[$kd] = $cant;
            $this->resto[$k] -= $cant;
            $resp = $this->almacenes[$p['aid']]['resp'];
            $this->plan[$k]['eventos'][] = ['fecha' => $fecha, 'tipo' => 'Salida', 'cant' => $cant, 'motivo' => 'Traslado_salida', 'factura' => null, 'usuario' => $resp,
                'obs' => 'Traslado al '.DB::table('almacenes')->where('id', $dest)->value('nombre_almacen')];
            $this->plan[$kd]['eventos'][] = ['fecha' => $fecha, 'tipo' => 'Entrada', 'cant' => $cant, 'motivo' => 'Traslado_entrada', 'factura' => null,
                'usuario' => $this->almacenes[$dest]['resp'], 'obs' => 'Traslado desde '.DB::table('almacenes')->where('id', $p['aid'])->value('nombre_almacen')];
            $hechos++;
        }

        // 3 mermas que dejan el stock bajo el mínimo (para probar las alertas de stock)
        $bajos = 0;
        foreach ($this->plan as $k => $p) {
            if ($bajos >= 3) {
                break;
            }
            $min = $this->prodInfo[$p['pid']]['min'];
            $objetivo = (int) floor($min * 0.6);
            if ($p['inicial'] > 0 && $this->resto[$k] > $min && $this->prodInfo[$p['pid']]['cat'] !== 'Equipo' && $this->resto[$k] - $objetivo > 0) {
                $cant = $this->resto[$k] - $objetivo;
                $this->resto[$k] = $objetivo;
                $this->plan[$k]['eventos'][] = ['fecha' => '2026-11-03', 'tipo' => 'Salida', 'cant' => $cant, 'motivo' => 'Ajuste_salida', 'factura' => null,
                    'usuario' => $this->almacenes[$p['aid']]['resp'], 'obs' => 'Merma por humedad y brotación en el almacén (inventario físico).'];
                $bajos++;
            }
        }

        foreach ($this->plan as $k => $p) {
            $eventos = $p['eventos'];
            if ($p['inicial'] > 0) {
                array_unshift($eventos, ['fecha' => $p['fecha'], 'tipo' => 'Entrada', 'cant' => $p['inicial'], 'motivo' => 'Compra',
                    'factura' => null, 'usuario' => $this->almacenes[$p['aid']]['resp'], 'obs' => 'Compra de reposición para la campaña 2026/27.']);
            }
            usort($eventos, fn ($a, $b) => [$a['fecha'], $a['tipo'] === 'Entrada' ? 0 : 1] <=> [$b['fecha'], $b['tipo'] === 'Entrada' ? 0 : 1]);
            $stock = 0.0;
            $ultima = null;
            foreach ($eventos as $e) {
                $antes = $stock;
                $stock += $e['tipo'] === 'Entrada' ? $e['cant'] : -$e['cant'];
                if ($stock < 0) {
                    throw new RuntimeException("Stock negativo en $k");
                }
                $this->ins('movimientos', [
                    'id_producto' => $p['pid'], 'id_almacen' => $p['aid'], 'id_factura' => $e['factura'], 'id_usuario' => $e['usuario'],
                    'tipo' => $e['tipo'], 'cantidad' => $e['cant'], 'stock_anterior' => $antes, 'stock_posterior' => $stock,
                    'motivo' => $e['motivo'], 'observaciones' => $e['obs'], 'fecha_movimiento' => $e['fecha'],
                    'created_at' => $this->ts($e['fecha']), 'updated_at' => $this->ts($e['fecha']),
                ]);
                $ultima = $e['fecha'];
            }
            $min = $this->prodInfo[$p['pid']]['min'];
            $this->ins('inventarios', [
                'id_producto' => $p['pid'], 'id_almacen' => $p['aid'], 'stock_actual' => $stock, 'stock_minimo' => $min,
                'stock_maximo' => max($p['inicial'] * 2, $min * 4), 'ultima_actualizacion' => $this->ts($ultima ?? $p['fecha']),
                'created_at' => $this->ts($p['fecha']), 'updated_at' => $this->ts($ultima ?? $p['fecha']),
            ]);
        }
    }

    // ------------------------------------------------------------ productos recomendados por enfermedad
    private function recomendaciones(): void
    {
        $nota = 'Dosis referencial: seguir la etiqueta del fabricante y la indicación del agrónomo.';
        $filas = [
            ['CNN-TT', 'AGQ-MET-MZ', 'Curativo', '2,5 g por litro de agua; máximo 2 aplicaciones por campaña', 'Alta'],
            ['CNN-TT', 'AGQ-MAN-80', 'Preventivo', '2,5 g por litro; cada 7 a 10 días en época de lluvias', 'Alta'],
            ['CNN-TT', 'AGQ-CLO-72', 'Preventivo', '2 ml por litro; cada 7 a 10 días', 'Media'],
            ['CNN-TT', 'AGQ-CIM-MZ', 'Curativo', '2,5 g por litro al aparecer los primeros síntomas', 'Alta'],
            ['CNN-TT', 'FER-SPK', 'Apoyo', '200 kg por hectárea en el fondo de siembra', 'Baja'],
            ['CNN-TTP', 'AGQ-MAN-80', 'Preventivo', '2,5 g por litro cada 10 días', 'Alta'],
            ['CNN-TTP', 'AGQ-CLO-72', 'Control', '2 ml por litro', 'Media'],
            ['CNN-TTP', 'FER-SPK', 'Apoyo', 'Balance nutricional: potasio mejora la tolerancia', 'Baja'],
            ['CNN-MB', 'S-005', 'Preventivo', 'Usar semilla certificada libre de la bacteria', 'Alta'],
            ['CNN-MB', 'BIO-BAC', 'Apoyo', '2 ml por litro a la siembra', 'Baja'],
            ['CNN-RZ', 'AGQ-CAR-50', 'Curativo', 'Tratamiento de semilla por inmersión durante 10 minutos', 'Alta'],
            ['CNN-RZ', 'BIO-TRI', 'Preventivo', '5 g por litro en el tratamiento de semilla', 'Media'],
            ['CNN-PVY', 'AGQ-IMI-35', 'Control', '0,5 ml por litro contra pulgones transmisores', 'Media'],
            ['CNN-PVY', 'S-006', 'Preventivo', 'Usar semilla certificada', 'Alta'],
            ['ENF-SAR', 'S-007', 'Preventivo', 'Usar semilla sana certificada', 'Media'],
            ['ENF-SAR', 'FER-HUM', 'Apoyo', '2 a 3 toneladas por hectárea; evitar el exceso de cal', 'Baja'],
            ['ENF-PLN', 'S-008', 'Preventivo', 'Semilla certificada y sana', 'Alta'],
            ['ENF-PLN', 'BIO-BAC', 'Apoyo', '2 ml por litro al suelo', 'Baja'],
            ['ENF-FUS', 'AGQ-CAR-50', 'Curativo', 'Tratamiento de semilla por inmersión', 'Media'],
            ['ENF-FUS', 'BIO-TRI', 'Preventivo', '5 g por litro en la semilla y el suelo', 'Media'],
            ['ENF-VER', 'BIO-TRI', 'Preventivo', 'Aplicación al suelo a la siembra', 'Baja'],
            ['ENF-NQP', 'S-005', 'Preventivo', 'Semilla certificada y rotación de 4 años', 'Alta'],
            ['ENF-NQP', 'FER-HUM', 'Apoyo', 'Materia orgánica para mejorar el suelo', 'Baja'],
            ['ENF-GOR', 'AGQ-CIP-25', 'Control', '1 ml por litro dirigido a los adultos al inicio de la emergencia', 'Alta'],
            ['ENF-GOR', 'BIO-BEA', 'Control', '5 g por litro; aplicar al follaje y al cuello de la planta', 'Media'],
            ['ENF-POL', 'AGQ-CIP-25', 'Control', '1 ml por litro', 'Media'],
            ['ENF-POL', 'BIO-BEA', 'Preventivo', '5 g por litro', 'Media'],
            ['ENF-MIN', 'AGQ-IMI-35', 'Control', '0,5 ml por litro', 'Media'],
            ['ENF-MIN', 'AGQ-CIP-25', 'Control', '1 ml por litro', 'Baja'],
            ['ENF-PLR', 'AGQ-IMI-35', 'Control', '0,5 ml por litro contra pulgones', 'Alta'],
            ['ENF-PLR', 'S-006', 'Preventivo', 'Usar semilla certificada', 'Alta'],
            ['ENF-PUL', 'AGQ-CIP-25', 'Control', '1 ml por litro solo si la defoliación es fuerte', 'Baja'],
            ['ENF-HEL', 'FER-SPK', 'Apoyo', '150 a 200 kg por hectárea: el potasio mejora la resistencia', 'Media'],
            ['ENF-HEL', 'FER-HUM', 'Apoyo', 'Mejora la retención de humedad y de calor del suelo', 'Baja'],
        ];
        foreach ($filas as [$enf, $prod, $tipo, $dosis, $prio]) {
            $this->ins('enfermedad_producto_recomendados', [
                'id_enfermedad' => DB::table('enfermedades')->where('codigo_enfermedad', $enf)->value('id'),
                'id_producto' => $this->prod[$prod], 'tipo_recomendacion' => $tipo, 'dosis_recomendada' => $dosis,
                'prioridad' => $prio, 'observaciones' => $nota, 'activo' => true,
                'created_at' => $this->ts('2026-07-05'), 'updated_at' => $this->ts('2026-07-05'),
            ]);
        }
    }

    // ------------------------------------------------------------ inspecciones, diagnósticos y alertas
    private function inspeccionesDiagnosticosAlertas(): void
    {
        $dir = storage_path('app/public/diagnosticos');
        // Fotos ya existentes en el sistema (por enfermedad). Se copian con otro nombre.
        $fotos = [
            'CNN-TT' => ['06iVgx1bALXFZTNXzF4fyKYq3tD614tgfBSp96S0.jpg', 'xtB3AuspIOPIlfhkRYynOhPHOD7GDXJWhY2fA2TU.jpg', 's2PjWQ6xuzGt5DfOKmzGZdPKHVjtB6CA2Smbe0Lq.jpg'],
            'CNN-TTP' => ['9js2i8HUccj8IuZRmPVCrxio9GMX4L6UMUrtV95c.jpg', '5Y25XZauWMtgAnR4Bz1gwcsz14sbUJaSpRjhcRv5.jpg'],
            'CNN-RZ' => ['kmuHqAwyOkKy09GPBbVFkNNnON0pckRIdpqOIK1B.jpg', 'oZulSBVzagrrJ0oBGh0KopOqJlOcwRg8awklRgIT.jpg'],
            'CNN-MB' => ['4h3S5vBocwVA4yULnPnSPkgQbCSM7v9q9TJ1wBSV.jpg'],
        ];
        $fotos = $this->completarFotos($dir, $fotos);

        $coseTemp = collect($this->cultivos)->where('temporada', '2526')->where('estado', 'Cosechado')->values();
        $activos = collect($this->cultivos)->where('estado', 'Activo')->values();
        // [cultivo, enfermedad, severidad, % afectadas, fecha visita, destino de la alerta]
        $casos = [
            [$coseTemp[0], 'CNN-TT', 'Severa', 38, '2026-01-14', 'resuelta'],
            [$coseTemp[1], 'CNN-TT', 'Muy_severa', 62, '2026-01-28', 'resuelta'],
            [$coseTemp[2], 'CNN-TTP', 'Severa', 33, '2026-02-04', 'vencida'],
            [$coseTemp[3], 'CNN-TT', 'Moderada', 24, '2026-02-12', 'resuelta'],
            [$coseTemp[4], 'CNN-TT', 'Severa', 41, '2026-02-19', 'ignorada'],
            [$coseTemp[5], 'CNN-RZ', 'Moderada', 22, '2026-02-26', 'resuelta'],
            [$coseTemp[6], 'CNN-TTP', 'Severa', 36, '2026-03-05', 'vencida'],
            [$coseTemp[7], 'CNN-MB', 'Moderada', 28, '2026-03-17', 'resuelta'],
            [$activos[0], 'CNN-RZ', 'Moderada', 18, '2026-10-22', 'nueva'],
            [$activos[1], 'CNN-TT', 'Leve', 9, '2026-10-29', 'tratamiento'],
        ];

        $svc = app(DiagnosticoService::class);
        $metodo = new ReflectionMethod($svc, 'generarAlertaSiEsNecesario');
        $metodo->setAccessible(true);

        foreach ($casos as $i => [$cultivo, $cod, $sev, $pct, $fecha, $destino]) {
            $agronomo = $this->pick(self::AGRONOMOS);
            $inspector = $this->pick(array_merge(self::TECNICOS, self::AGRONOMOS));
            $dias = (int) Carbon::parse($cultivo['siembra'])->diffInDays($fecha);
            $lluvia = (int) Carbon::parse($fecha)->format('n') <= 3;
            $hora = $this->r(8, 15);
            $inspId = $this->ins('inspecciones', [
                'id_usuario' => $inspector, 'id_parcela' => $cultivo['parcela'], 'id_cultivo' => $cultivo['id'],
                'fecha_visita' => $fecha, 'hora_visita' => sprintf('%02d:%02d:00', $hora, $this->pick([0, 15, 30, 45])),
                'id_etapa_fenologica' => $this->etapaPorDias($dias),
                'condicion_climatica' => $lluvia ? $this->pick(['Lluvioso', 'Nublado', 'Mixto']) : $this->pick(['Soleado', 'Ventoso', 'Helado']),
                'temperatura_c' => $lluvia ? $this->f(6, 13, 1) : $this->f(3, 14, 1),
                'humedad_relativa_pct' => $lluvia ? $this->r(65, 92) : $this->r(35, 60),
                'pct_plantas_afectadas' => $pct,
                'observaciones' => $this->observacionInspeccion($cod),
                'estado' => 'Validada', 'tipo_inspeccion' => 'Cultivo', 'id_programado_por' => $this->pick(self::ADMINS),
                'id_validado_por' => $agronomo,
                'fecha_inicio' => $this->ts($fecha, $hora), 'fecha_finalizacion' => $this->ts($fecha, $hora + 1),
                'fecha_validacion' => $this->ts(Carbon::parse($fecha)->addDay()->format('Y-m-d')),
                'created_at' => $this->ts(Carbon::parse($fecha)->subDays(3)->format('Y-m-d')), 'updated_at' => $this->ts($fecha),
            ]);

            $origen = $fotos[$cod][$i % count($fotos[$cod])];
            $nuevo = Str::random(40).'.jpg';
            File::copy("$dir/$origen", "$dir/$nuevo");
            $this->copias[] = "$dir/$nuevo";
            $enf = DB::table('enfermedades')->where('codigo_enfermedad', $cod)->first();
            $esCnn = in_array($cod, ['CNN-TT', 'CNN-TTP'], true);
            $top3 = null;
            if ($esCnn) {
                // La CNN v1 solo distingue tizón tardío, tizón temprano y sana.
                $conf = $this->f(82, 99.6);
                $clases = [
                    'CNN-TT' => ['late_blight', 'Tizón Tardío'], 'CNN-TTP' => ['early_blight', 'Tizón Temprano'],
                    'CNN-SANA' => ['healthy', 'Sana (Sin enfermedad)'],
                ];
                $otros = array_values(array_filter(array_keys($clases), fn ($c) => $c !== $cod));
                $resto = round(100 - $conf, 2);
                $segunda = round($resto * $this->f(0.55, 0.8), 2);
                $top3 = [['clase' => $clases[$cod][0], 'nombre' => $clases[$cod][1], 'codigo_enfermedad' => $cod, 'confianza' => $conf]];
                foreach ([$segunda, round($resto - $segunda, 2)] as $j => $c) {
                    $top3[] = ['clase' => $clases[$otros[$j]][0], 'nombre' => $clases[$otros[$j]][1], 'codigo_enfermedad' => $otros[$j], 'confianza' => $c];
                }
            } else {
                $conf = $this->f(70, 90);
            }
            $diagId = $this->ins('diagnosticos_cnn', [
                'id_inspeccion' => $inspId, 'id_enfermedad' => $enf->id, 'ruta_fotografia' => "diagnosticos/$nuevo",
                'nombre_archivo' => 'IMG_'.Carbon::parse($fecha)->format('Ymd').'_'.sprintf('%02d%02d%02d', $hora, $this->r(0, 59), $this->r(0, 59)).'.jpg',
                'porcentaje_confianza' => $conf,
                'top3_predicciones' => $top3 ? json_encode($top3, JSON_UNESCAPED_UNICODE) : null,
                'modelo_version' => $esCnn ? 'PROINPA-ResNet50-v1.0' : 'Manual-v1.0', 'nivel_severidad' => $sev,
                'recomendacion' => $enf->medidas_control, 'estado_validacion' => 'Confirmado', 'validado_por' => $agronomo,
                'observaciones_validacion' => 'Síntomas confirmados en campo por el agrónomo.',
                'fecha_validacion' => $this->ts(Carbon::parse($fecha)->addDay()->format('Y-m-d')),
                'created_at' => $this->ts($fecha, $hora), 'updated_at' => $this->ts(Carbon::parse($fecha)->addDay()->format('Y-m-d')),
            ]);

            // Alerta generada por el motor real del sistema (prioridad inteligente).
            $metodo->invoke($svc, DiagnosticoCNN::findOrFail($diagId), $agronomo);
            $this->ajustarAlerta($diagId, $fecha, $destino, $agronomo, $cod);
        }
    }

    /** Si falta alguna foto de origen, usa cualquiera que exista. */
    private function completarFotos(string $dir, array $fotos): array
    {
        $existentes = array_map('basename', glob("$dir/*.jpg"));
        foreach ($fotos as $cod => $lista) {
            $validas = array_values(array_filter($lista, fn ($f) => in_array($f, $existentes, true)));
            if (! $validas) {
                throw new RuntimeException("No hay fotos de origen para $cod en $dir");
            }
            $fotos[$cod] = $validas;
        }

        return $fotos;
    }

    private function observacionInspeccion(string $cod): string
    {
        return match ($cod) {
            'CNN-TT' => 'Manchas acuosas oscuras en hojas y tallos tras días de lluvia; se observa esporulación blanca en el envés.',
            'CNN-TTP' => 'Manchas concéntricas en hojas bajas; la enfermedad avanza desde el follaje inferior.',
            'CNN-RZ' => 'Lesiones pardas en tallos y estolones; brotes con emergencia despareja.',
            default => 'Marchitez de plantas aisladas con exudado bacteriano en el corte del tallo.',
        };
    }

    private function ajustarAlerta(int $diagId, string $fecha, string $destino, int $agronomo, string $cod): void
    {
        $alerta = DB::table('alertas')->where('id_diagnostico', $diagId)->first();
        if (! $alerta) {
            throw new RuntimeException("El motor no generó alerta para el diagnóstico $diagId ($cod)");
        }
        $emision = Carbon::parse($fecha)->addDay();
        $dias = match ($alerta->nivel_riesgo) {
            'Critico' => 1, 'Alto' => 2, 'Medio' => 3, default => 5,
        };
        $base = [
            'fecha_emision' => $emision->format('Y-m-d'),
            'fecha_limite_accion' => $emision->copy()->addDays($dias)->format('Y-m-d'),
            'created_at' => $this->ts($emision->format('Y-m-d'), 9), 'updated_at' => $this->ts($emision->format('Y-m-d'), 9),
        ];
        $extra = match ($destino) {
            'resuelta' => [
                'estado' => 'Atendida', 'leida' => true, 'fecha_lectura' => $this->ts($emision->format('Y-m-d'), 10),
                'id_responsable' => $agronomo, 'estado_seguimiento' => 'Resuelta', 'progreso_seguimiento' => 100,
                'plan_manejo' => 'Retirar el material vegetal gravemente afectado y aplicar el tratamiento fitosanitario recomendado.',
                'accion_tomada' => 'Se aplicó el tratamiento recomendado y se retiraron las plantas más afectadas.',
                'fecha_inicio_tratamiento' => $this->ts($emision->copy()->addDay()->format('Y-m-d'), 8),
                'ultima_revision_at' => $this->ts($emision->copy()->addDays(9)->format('Y-m-d'), 11),
                'proxima_revision' => $this->ts($emision->copy()->addDays(10)->format('Y-m-d'), 9),
                'resultado_seguimiento' => 'Controlado',
                'observacion_cierre' => 'La afectación quedó controlada y no se observó progresión en la revisión final.',
                'fecha_resolucion' => $this->ts($emision->copy()->addDays(10)->format('Y-m-d'), 12),
            ],
            'vencida' => ['estado' => 'Vencida', 'leida' => false],
            'ignorada' => ['estado' => 'Ignorada', 'leida' => true, 'fecha_lectura' => $this->ts($emision->format('Y-m-d'), 15),
                'estado_seguimiento' => 'Descartada', 'observacion_cierre' => 'El productor decidió no tratar esta parcela.'],
            'tratamiento' => [
                'estado' => 'Activa', 'leida' => true, 'fecha_lectura' => $this->ts($emision->format('Y-m-d'), 10),
                'id_responsable' => $agronomo, 'estado_seguimiento' => 'En_tratamiento', 'progreso_seguimiento' => 35,
                'plan_manejo' => 'Aplicación de fungicida protectante cada 7 días y monitoreo de la parcela.',
                'fecha_inicio_tratamiento' => $this->ts($emision->copy()->addDay()->format('Y-m-d'), 8),
                'proxima_revision' => $this->ts(Carbon::parse(self::HOY)->addDays(2)->format('Y-m-d'), 9),
            ],
            default => ['estado' => 'Activa', 'leida' => false],
        };
        DB::table('alertas')->where('id', $alerta->id)->update($base + $extra);
    }
}
