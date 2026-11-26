<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruletas', function (Blueprint $table) {
            $table->string('titulo_imagen_path')->nullable()->after('descripcion');
            $table->boolean('usar_imagen_titulo')->default(false)->after('titulo_imagen_path');
        });
    }

    public function down(): void
    {
        Schema::table('ruletas', function (Blueprint $table) {
            $table->dropColumn(['titulo_imagen_path', 'usar_imagen_titulo']);
        });
    }
};
