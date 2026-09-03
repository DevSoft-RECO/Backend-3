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
        Schema::table('cartilla_registros', function (Blueprint $table) {
            $table->string('codigo_cliente', 10)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cartilla_registros', function (Blueprint $table) {
            $table->string('codigo_cliente', 10)->nullable(false)->change();
        });
    }
};
