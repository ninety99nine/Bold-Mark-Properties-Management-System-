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
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->string('allocated_by_name')->nullable()->after('proof_of_payment_path');
            $table->timestamp('allocated_at')->nullable()->after('allocated_by_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->dropColumn(['allocated_by_name', 'allocated_at']);
        });
    }
};
