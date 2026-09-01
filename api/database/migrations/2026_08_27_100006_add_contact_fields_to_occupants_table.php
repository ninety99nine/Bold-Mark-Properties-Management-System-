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
        Schema::table('occupants', function (Blueprint $table) {
            $table->string('postal_address')->nullable()->after('id_number');
            $table->string('car_registration', 50)->nullable()->after('postal_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('occupants', function (Blueprint $table) {
            $table->dropColumn(['postal_address', 'car_registration']);
        });
    }
};
