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
        // Recreación limpia de la tabla 'users' para soportar sincronización JIT desde la App Madre
        Schema::dropIfExists('users');
        
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // Clave primaria: ID Central de App Madre
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedBigInteger('agencia_id')->nullable();
            $table->string('puesto')->nullable();
            $table->text('roles_list')->nullable();
            $table->text('permissions_list')->nullable();
            $table->string('jti')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        
        // Revertir a la estructura estándar inicial por defecto
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
