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
            $table->date('source_payment_date')->nullable()->after('notas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cartilla_registros', function (Blueprint $table) {
            $table->dropColumn('source_payment_date');
        });
    }
};
