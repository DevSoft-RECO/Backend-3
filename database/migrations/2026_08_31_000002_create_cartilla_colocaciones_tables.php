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
        Schema::create('cartilla_colocaciones_importaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('nombre_usuario');
            $table->string('nombre_archivo');
            $table->integer('total_filas')->default(0);
            $table->integer('filas_elegibles')->default(0);
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users');
        });

        Schema::create('cartilla_colocaciones_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('importacion_id');
            $table->unsignedBigInteger('agencia_id');
            $table->string('codigo_cliente', 10);
            $table->string('numero_cuenta', 20);
            $table->decimal('monto', 12, 2);
            $table->date('fecha_pago');
            $table->date('fecha_sugerida_pago');
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE, RECLAMADO
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->unsignedBigInteger('reclamado_por_usuario_id')->nullable();
            $table->timestamp('reclamado_en')->nullable();
            $table->timestamps();

            $table->foreign('importacion_id')->references('id')->on('cartilla_colocaciones_importaciones')->onDelete('cascade');
            $table->foreign('agencia_id')->references('id')->on('cartilla_agencias');
            $table->foreign('registro_id')->references('id')->on('cartilla_registros')->onDelete('set null');
            $table->foreign('reclamado_por_usuario_id')->references('id')->on('users');

            $table->index(['numero_cuenta', 'fecha_pago'], 'idx_colocacion_cuenta_fecha');
            $table->index(['agencia_id', 'estado'], 'idx_colocacion_agencia_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartilla_colocaciones_pagos');
        Schema::dropIfExists('cartilla_colocaciones_importaciones');
    }
};
