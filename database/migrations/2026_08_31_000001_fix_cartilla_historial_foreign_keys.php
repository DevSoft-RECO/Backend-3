<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartilla_historial_registros', function (Blueprint $table) {
            $table->dropForeign(['registro_id']);
            $table->unsignedBigInteger('registro_id')->nullable()->change();
            $table->foreign('registro_id')->references('id')->on('cartilla_registros')->onDelete('set null');
        });

        Schema::table('cartilla_historial_inventario', function (Blueprint $table) {
            $table->dropForeign(['movimiento_id']);
            $table->unsignedBigInteger('movimiento_id')->nullable()->change();
            $table->foreign('movimiento_id')->references('id')->on('cartilla_movimientos_inventario')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cartilla_historial_registros', function (Blueprint $table) {
            $table->dropForeign(['registro_id']);
            $table->foreign('registro_id')->references('id')->on('cartilla_registros')->onDelete('cascade');
        });

        Schema::table('cartilla_historial_inventario', function (Blueprint $table) {
            $table->dropForeign(['movimiento_id']);
            $table->foreign('movimiento_id')->references('id')->on('cartilla_movimientos_inventario')->onDelete('cascade');
        });
    }
};
