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
        Schema::create('cartilla_agencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();          // AG01, AG02...AG18
            $table->string('nombre');                         // Agencia 01 Central
            $table->string('area_financiera', 20)->unique();  // GT0012600
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cartilla_promocionales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cartilla_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();   // 'mecanica', 'alertas_agencia', 'alertas_central', 'info_evento'
            $table->json('valor');
            $table->timestamps();
        });

        Schema::create('cartilla_notas_rapidas', function (Blueprint $table) {
            $table->id();
            $table->string('texto');
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cartilla_recordatorios', function (Blueprint $table) {
            $table->id();
            $table->string('texto');
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cartilla_registros', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();                    // AGXX-#######
            $table->unsignedBigInteger('agencia_id');                  // FK a cartilla_agencias
            $table->unsignedBigInteger('usuario_id');                  // FK a users (SSO)
            $table->string('nombre_colaborador');                      // Snapshot del nombre
            $table->string('codigo_cliente', 10);                     // 5-7 dígitos
            $table->string('accion');                                  // CREDITO_NUEVO, PLAZO_FIJO, MOTOCICLETA, PAGO_PUNTUAL
            $table->string('tipo_operacion')->nullable();              // AMPLIACION, NUEVO, RENOVACION, FINANCIADA, AL_CONTADO, PRESENCIAL
            $table->decimal('monto', 12, 2)->nullable();               // Monto en Quetzales
            $table->string('numero_cuenta', 20)->nullable();           // 126XXXXXXXXXXXX
            $table->integer('stickers')->default(0);                   // Stickers asignados (calculados)
            $table->boolean('cartilla_nueva')->default(false);         // ¿Se entregó nueva cartilla?
            $table->boolean('cartilla_completada')->default(false);    // ¿Se completó la cartilla?
            $table->boolean('sorteo')->default(false);                 // Participación en sorteo
            $table->string('promocional_entregado')->nullable();       // Nombre del promocional
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('agencia_id')->references('id')->on('cartilla_agencias');
            $table->foreign('usuario_id')->references('id')->on('users');

            $table->index(['codigo_cliente', 'accion'], 'idx_asociado_accion');
        });

        Schema::create('cartilla_historial_registros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->string('nombre_usuario');
            $table->string('estado_cambio');               // EDITADO o ELIMINADO
            $table->json('snapshot');                       // Copia completa del registro anterior
            $table->timestamp('ejecutado_en');
            $table->timestamps();

            $table->foreign('registro_id')->references('id')->on('cartilla_registros')->onDelete('set null');
            $table->foreign('usuario_id')->references('id')->on('users');
        });

        Schema::create('cartilla_inventario_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agencia_id')->nullable();   // null = Central de Mercadeo
            $table->string('recurso');                               // STICKERS, CARTILLAS, PROMOCIONAL
            $table->string('nombre_promocional')->nullable();        // Solo si recurso = PROMOCIONAL
            $table->integer('cantidad')->default(0);
            $table->timestamps();

            $table->foreign('agencia_id')->references('id')->on('cartilla_agencias');
            $table->unique(['agencia_id', 'recurso', 'nombre_promocional'], 'uk_stock_agencia_recurso');
        });

        Schema::create('cartilla_movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();                   // MOV-XXXXXXX
            $table->unsignedBigInteger('agencia_id')->nullable();     // null = Central
            $table->unsignedBigInteger('usuario_id');
            $table->string('nombre_usuario');                         // Snapshot
            $table->string('recurso');                                // STICKERS, CARTILLAS, PROMOCIONAL
            $table->string('nombre_promocional')->nullable();
            $table->string('tipo_movimiento');                        // INGRESO o EGRESO
            $table->integer('cantidad');
            $table->string('alcance');                                // central, traslado, consumo-registro, reposicion
            $table->string('codigo_registro')->nullable();            // Vínculo al registro si es consumo
            $table->unsignedBigInteger('agencia_destino_id')->nullable(); // Para traslados
            $table->string('codigo_cliente', 10)->nullable();        // Para reposiciones
            $table->text('detalle')->nullable();
            $table->boolean('es_manual')->default(false);             // true = creado por Mercadeo, editable
            $table->timestamps();

            $table->foreign('agencia_id')->references('id')->on('cartilla_agencias');
            $table->foreign('agencia_destino_id')->references('id')->on('cartilla_agencias');
            $table->foreign('usuario_id')->references('id')->on('users');
        });

        Schema::create('cartilla_historial_inventario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movimiento_id')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->string('nombre_usuario');
            $table->string('estado_cambio');               // EDITADO o ELIMINADO
            $table->json('snapshot');
            $table->boolean('restaurado')->default(false);
            $table->timestamp('ejecutado_en');
            $table->timestamps();

            $table->foreign('movimiento_id')->references('id')->on('cartilla_movimientos_inventario')->onDelete('set null');
            $table->foreign('usuario_id')->references('id')->on('users');
        });

        Schema::create('cartilla_descartes_alertas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('clave_alerta');           // "AG01_STICKERS", "CENTRAL_CARTILLAS"
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users');
            $table->unique(['usuario_id', 'clave_alerta']);
        });

        Schema::create('cartilla_historial_configuracion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('nombre_usuario');
            $table->string('seccion');                // mecanica, promocionales, notas, recordatorios, alertas, info_evento
            $table->string('resumen');                // Descripción breve del cambio
            $table->json('valor_anterior')->nullable();
            $table->json('valor_nuevo');
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartilla_historial_configuracion');
        Schema::dropIfExists('cartilla_descartes_alertas');
        Schema::dropIfExists('cartilla_historial_inventario');
        Schema::dropIfExists('cartilla_movimientos_inventario');
        Schema::dropIfExists('cartilla_inventario_stocks');
        Schema::dropIfExists('cartilla_historial_registros');
        Schema::dropIfExists('cartilla_registros');
        Schema::dropIfExists('cartilla_recordatorios');
        Schema::dropIfExists('cartilla_notas_rapidas');
        Schema::dropIfExists('cartilla_configuracion');
        Schema::dropIfExists('cartilla_promocionales');
        Schema::dropIfExists('cartilla_agencias');
    }
};
