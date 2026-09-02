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
            $table->string('block_number', 50)->nullable()->after('unit_number');
            $table->string('door_number', 50)->nullable()->after('section');
            $table->string('customer_code', 30)->nullable()->after('door_number');
            $table->boolean('billing_pdf')->default(false)->after('rent_amount');

            $table->index('customer_code');
            $table->unique(['community_id', 'customer_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['community_id', 'customer_code']);
            $table->dropIndex(['customer_code']);
            $table->dropColumn(['block_number', 'door_number', 'customer_code', 'billing_pdf']);
        });
    }
};
