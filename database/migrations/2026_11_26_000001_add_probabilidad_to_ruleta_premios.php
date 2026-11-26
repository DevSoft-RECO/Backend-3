<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruleta_premios', function (Blueprint $table) {
            $table->decimal('probabilidad', 6, 2)->default(10.00)->after('modo_visualizacion');
        });

        Schema::table('ruleta_giros', function (Blueprint $table) {
            $table->string('agencia_nombre')->nullable()->after('dispositivo');
            $table->string('observacion_ganador', 500)->nullable()->after('agencia_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('ruleta_giros', function (Blueprint $table) {
            $table->dropColumn(['agencia_nombre', 'observacion_ganador']);
        });

        Schema::table('ruleta_premios', function (Blueprint $table) {
            $table->dropColumn('probabilidad');
        });
    }
};
