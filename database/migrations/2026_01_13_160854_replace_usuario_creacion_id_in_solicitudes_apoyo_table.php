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
            $table->dropColumn('usuario_creacion_id');
            $table->string('usuario_creacion')->after('comentario_solicitud')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_apoyo', function (Blueprint $table) {
            $table->dropColumn('usuario_creacion');
            $table->unsignedBigInteger('usuario_creacion_id')->nullable();
        });
    }
};
