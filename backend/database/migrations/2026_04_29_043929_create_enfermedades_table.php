<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de enfermedades conocidas que detecta el sistema CNN
        Schema::create('enfermedades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comun', 150)->unique();     // "Tizón Tardío"
            $table->string('nombre_cientifico', 200)->nullable(); // "Phytophthora infestans"
            $table->string('codigo_enfermedad', 20)->unique(); // "TT-001" (usado por la CNN)
            $table->enum('tipo', ['Hongo', 'Bacteria', 'Virus', 'Nematodo', 'Otro']);
            $table->text('sintomas')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('medidas_control')->nullable();       // Recomendaciones de manejo
            $table->enum('nivel_riesgo_base', ['Bajo', 'Medio', 'Alto', 'Critico'])->default('Medio');
            $table->string('imagen_referencia')->nullable();   // Ruta imagen de referencia
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Enfermedades principales del cultivo de papa en Bolivia
        DB::table('enfermedades')->insert([
            [
                'nombre_comun'       => 'Tizón Tardío',
                'nombre_cientifico'  => 'Phytophthora infestans',
                'codigo_enfermedad'  => 'CNN-TT',
                'tipo'               => 'Hongo',
                'sintomas'           => 'Manchas necróticas irregulares color café-oscuro en hojas, bordes acuosos, micelio blanco en el envés bajo humedad.',
                'descripcion'        => 'Enfermedad más destructiva de la papa. Puede destruir un cultivo en 7-10 días bajo condiciones favorables.',
                'medidas_control'    => 'Aplicar fungicidas sistémicos (Metalaxil) y de contacto (Mancozeb). Eliminar plantas enfermas. Usar variedades resistentes.',
                'nivel_riesgo_base'  => 'Critico',
                'activo'             => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'nombre_comun'       => 'Tizón Temprano',
                'nombre_cientifico'  => 'Alternaria solani',
                'codigo_enfermedad'  => 'CNN-TTP',
                'tipo'               => 'Hongo',
                'sintomas'           => 'Manchas circulares con anillos concéntricos (aspecto diana) en hojas inferiores. Color café-claro.',
                'descripcion'        => 'Afecta principalmente hojas maduras. Favorecido por estrés hídrico y temperaturas cálidas.',
                'medidas_control'    => 'Aplicar Mancozeb o Clorotalonil. Fertilización adecuada de potasio. Riego regular.',
                'nivel_riesgo_base'  => 'Medio',
                'activo'             => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'nombre_comun'       => 'Marchitez Bacteriana',
                'nombre_cientifico'  => 'Ralstonia solanacearum',
                'codigo_enfermedad'  => 'CNN-MB',
                'tipo'               => 'Bacteria',
                'sintomas'           => 'Marchitez súbita de plantas. Coloración marrón en el interior del tallo. Exudado lechoso al corte.',
                'descripcion'        => 'Enfermedad del suelo de difícil control. Puede persistir años en el suelo.',
                'medidas_control'    => 'No existe control químico efectivo. Rotación de cultivos, uso de semilla certificada, desinfección de herramientas.',
                'nivel_riesgo_base'  => 'Critico',
                'activo'             => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'nombre_comun'       => 'Rizoctoniasis',
                'nombre_cientifico'  => 'Rhizoctonia solani',
                'codigo_enfermedad'  => 'CNN-RZ',
                'tipo'               => 'Hongo',
                'sintomas'           => 'Costras negras en tubérculos. Cancros en tallos. Retraso en la emergencia. Hojas en cuchara.',
                'descripcion'        => 'Afecta en suelos fríos y húmedos. Causa pérdidas en emergencia y calidad de tubérculos.',
                'medidas_control'    => 'Tratamiento de semilla con Carbendazim. Rotación de cultivos. Buen drenaje.',
                'nivel_riesgo_base'  => 'Alto',
                'activo'             => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'nombre_comun'       => 'Virus PVY (Papa virus Y)',
                'nombre_cientifico'  => 'Potato virus Y',
                'codigo_enfermedad'  => 'CNN-PVY',
                'tipo'               => 'Virus',
                'sintomas'           => 'Mosaico en hojas, necrosis de nervaduras, enrollamiento foliar, enanismo de plantas.',
                'descripcion'        => 'Transmitido por áfidos. Reduce rendimiento hasta 80% en plantas infectadas.',
                'medidas_control'    => 'Uso de semilla certificada libre de virus. Control de vectores (áfidos). Eliminación de plantas enfermas.',
                'nivel_riesgo_base'  => 'Alto',
                'activo'             => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'nombre_comun'       => 'Sana (Sin enfermedad)',
                'nombre_cientifico'  => null,
                'codigo_enfermedad'  => 'CNN-SANA',
                'tipo'               => 'Otro',
                'sintomas'           => 'Planta sin síntomas visibles de enfermedad.',
                'descripcion'        => 'Resultado del diagnóstico CNN cuando la planta está sana.',
                'medidas_control'    => 'Mantener buenas prácticas agronómicas y monitoreo preventivo.',
                'nivel_riesgo_base'  => 'Bajo',
                'activo'             => true,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('enfermedades');
    }
};
