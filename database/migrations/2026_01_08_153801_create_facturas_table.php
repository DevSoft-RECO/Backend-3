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
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_factura')->index();
            $table->string('numero_serie')->index();

            $table->foreignId('categoria_id')->constrained('categorias_facturas');

            $table->date('fecha_factura');
            $table->decimal('monto', 12, 2);

            $table->text('descripcion')->nullable();
            $table->string('nombre_emisor')->nullable();
            $table->string('nit_emisor')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
