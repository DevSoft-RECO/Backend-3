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
            // 1. Rename fecha_evento -> fecha_evento_inicio
            $table->renameColumn('fecha_evento', 'fecha_evento_inicio');

            // 2. Add fecha_evento_fin (after new fecha_evento_inicio)
            $table->date('fecha_evento_fin')->after('fecha_evento_inicio')->nullable();
            // Actually, rename happens first, but 'after' refers to current column state. Let's precise.

            // 3. Add comentario_aprobacion
            $table->text('comentario_aprobacion')->nullable()->after('fecha_aprobacion');

            // 4. Drop old photos
            $table->dropColumn(['path_foto_entrega', 'path_foto_conocimiento']);

            // 5. Add new PDF evidence
            $table->string('path_documento_evidencia')->nullable()->after('comentario_gestion'); // Position arbitrary, putting near other paths
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_apoyo', function (Blueprint $table) {
            $table->renameColumn('fecha_evento_inicio', 'fecha_evento');
            $table->dropColumn(['fecha_evento_fin', 'comentario_aprobacion', 'path_documento_evidencia']);
            $table->string('path_foto_entrega')->nullable();
            $table->string('path_foto_conocimiento')->nullable();
        });
    }
};
