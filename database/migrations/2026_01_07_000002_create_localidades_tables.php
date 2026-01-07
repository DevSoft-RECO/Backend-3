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
        // Departamentos
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // Municipios
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained();
            $table->string('nombre');
            $table->timestamps();
        });

        // Comunidades (Esta es la que guardas en la solicitud)
        Schema::create('comunidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained();
            $table->string('nombre');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comunidades');
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('departamentos');
    }
};
