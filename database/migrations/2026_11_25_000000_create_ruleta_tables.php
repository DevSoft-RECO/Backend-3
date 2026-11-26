<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruletas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('agencia_o_evento')->nullable();
            $table->foreignId('agencia_id')
                ->nullable()
                ->constrained('cartilla_agencias')
                ->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ruleta_premios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruleta_id')
                ->constrained('ruletas')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->string('imagen_path')->nullable();
            $table->string('modo_visualizacion', 20)->default('texto'); // 'texto' | 'imagen'
            $table->unsignedInteger('stock_inicial')->default(0);
            $table->unsignedInteger('stock_actual')->default(0);
            $table->boolean('stock_ilimitado')->default(false);
            $table->boolean('es_premio')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ruleta_giros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruleta_id')
                ->constrained('ruletas')
                ->cascadeOnDelete();
            $table->foreignId('premio_id')
                ->nullable()
                ->constrained('ruleta_premios')
                ->nullOnDelete();
            $table->string('premio_nombre');
            $table->boolean('es_premio')->default(true);
            $table->boolean('stock_descontado')->default(false);
            $table->string('dispositivo')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruleta_giros');
        Schema::dropIfExists('ruleta_premios');
        Schema::dropIfExists('ruletas');
    }
};
