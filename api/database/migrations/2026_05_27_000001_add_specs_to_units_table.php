<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('size_sqm', 8, 2)->nullable()->after('address');
            $table->unsignedTinyInteger('bedrooms')->nullable()->after('size_sqm');
            $table->unsignedTinyInteger('bathrooms')->nullable()->after('bedrooms');
            $table->unsignedTinyInteger('parking_bays')->nullable()->after('bathrooms');
            $table->string('floor_level', 50)->nullable()->after('parking_bays');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['size_sqm', 'bedrooms', 'bathrooms', 'parking_bays', 'floor_level']);
        });
    }
};
