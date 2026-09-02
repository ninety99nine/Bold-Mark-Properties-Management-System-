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
        Schema::table('units', function (Blueprint $table) {
            // WeConnectU owner sheet splits area into unit/garage/carport/parking (Sq m).
            $table->decimal('garage_size', 12, 2)->nullable()->after('unit_size');
            $table->decimal('carport_size', 12, 2)->nullable()->after('garage_size');
            $table->decimal('parking_size', 12, 2)->nullable()->after('carport_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['garage_size', 'carport_size', 'parking_size']);
        });
    }
};
