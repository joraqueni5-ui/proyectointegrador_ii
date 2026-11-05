<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();

            $table->string('nro_cotizacion', 20)->unique();

            $table->foreignId('id_productor')
                ->constrained('productores')
                ->restrictOnDelete();

            $table->foreignId('id_usuario')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('id_aprobador')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('convertida_factura_id')
                ->nullable()
                ->constrained('facturas')
                ->nullOnDelete();

            $table->date('fecha_emision')->default(DB::raw('CURRENT_DATE'));
            $table->date('fecha_vencimiento')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('monto_total', 12, 2)->default(0);

            $table->string('metodo_pago', 30)->default('Efectivo');

            $table->string('estado', 30)->default('Pendiente');
            $table->text('observaciones')->nullable();
            $table->text('motivo_rechazo')->nullable();

            $table->timestamp('aprobada_at')->nullable();
            $table->timestamp('rechazada_at')->nullable();
            $table->timestamp('convertida_at')->nullable();

            $table->timestamps();

            $table->index('id_productor');
            $table->index('id_usuario');
            $table->index('estado');
            $table->index('fecha_emision');
        });

        DB::statement("
            ALTER TABLE cotizaciones
            ADD CONSTRAINT cotizaciones_estado_check
            CHECK (estado IN ('Pendiente', 'Aprobada', 'Rechazada', 'Convertida', 'Anulada'))
        ");

        DB::statement("
            ALTER TABLE cotizaciones
            ADD CONSTRAINT cotizaciones_metodo_pago_check
            CHECK (metodo_pago IN ('Efectivo', 'Transferencia', 'QR', 'Credito', 'Otro'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
