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
        Schema::create('solicitudes_apoyo', function (Blueprint $table) {
            $table->id();

            // --- CONTROL DE ESTADO ---
            // Enum: SOLICITADO, EN_GESTION, APROBADO, FINALIZADO, RECHAZADO
            $table->string('estado')->default('SOLICITADO')->index();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamp('fecha_rechazo')->nullable(); // Analítica

            // --- ETAPA 1: SOLICITUD (Datos de entrada) ---
            $table->date('fecha_solicitud');
            $table->date('fecha_evento');

            $table->string('nombre_solicitante');
            $table->string('telefono', 20);
            $table->string('nombre_contacto')->nullable();

            // Ubicación
            $table->foreignId('comunidad_id')->constrained('comunidades');

            $table->text('comentario_solicitud');
            $table->string('path_documento_adjunto')->nullable(); // PDF Solicitud

            // Auditoría Etapa 1 (IDs externos del Token)
            $table->unsignedBigInteger('usuario_creacion_id');
            $table->unsignedBigInteger('agencia_id');

            // --- ETAPA 2: GESTIÓN (Revisión) ---
            $table->text('comentario_gestion')->nullable();
            $table->unsignedBigInteger('usuario_gestion_id')->nullable(); // ID externo
            $table->timestamp('fecha_inicio_gestion')->nullable(); // Analítica

            // --- ETAPA 3: APROBACIÓN (Formalización) ---
            $table->string('responsable_asignado')->nullable();
            $table->string('path_documento_firmado')->nullable(); // PDF Firmado
            $table->decimal('monto', 12, 2)->nullable();
            $table->foreignId('tipo_apoyo_id')->nullable()->constrained('tipos_apoyo');

            $table->unsignedBigInteger('usuario_aprobacion_id')->nullable(); // ID externo
            $table->timestamp('fecha_aprobacion')->nullable(); // Analítica

            // --- ETAPA 4: FINALIZACIÓN (Evidencias) ---
            $table->string('path_foto_entrega')->nullable();
            $table->string('path_foto_conocimiento')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_apoyo');
    }
};
