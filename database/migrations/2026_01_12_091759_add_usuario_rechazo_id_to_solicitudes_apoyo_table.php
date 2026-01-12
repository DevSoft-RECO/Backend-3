<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solicitudes_apoyo', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_rechazo_id')->nullable()->after('motivo_rechazo');
            $table->string('nombre_usuario_rechazo')->nullable()->after('usuario_rechazo_id');
            $table->string('nombre_usuario_gestion')->nullable()->after('usuario_gestion_id');
            $table->string('nombre_usuario_aprobacion')->nullable()->after('usuario_aprobacion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_apoyo', function (Blueprint $table) {
            $table->dropColumn(['usuario_rechazo_id', 'nombre_usuario_rechazo', 'nombre_usuario_gestion', 'nombre_usuario_aprobacion']);
        });
    }
};
