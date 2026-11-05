<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productores', function (Blueprint $table) {
            $table->string('ci_extension', 5)->nullable()->after('carnet_identidad');
        });
    }

    public function down(): void
    {
        Schema::table('productores', function (Blueprint $table) {
            $table->dropColumn('ci_extension');
        });
    }
};
