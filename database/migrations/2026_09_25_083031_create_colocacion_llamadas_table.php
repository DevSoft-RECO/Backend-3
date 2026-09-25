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
        Schema::create('cartilla_colocacion_llamadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('cartilla_colocaciones_pagos')->onDelete('cascade');
            $table->foreignId('agencia_id')->nullable()->constrained('cartilla_agencias')->onDelete('set null');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('estado'); // Pendiente, No contesta, Completada
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cartilla_colocacion_llamadas');
    }
};
