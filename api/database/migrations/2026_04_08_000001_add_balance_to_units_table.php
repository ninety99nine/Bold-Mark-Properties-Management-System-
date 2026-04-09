<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Stored balance: unallocated_credits − outstanding_amount.
            // negative → in arrears, zero → clear, positive → credit on account.
            // Kept in sync by UnitBalanceService::recalculate() whenever invoices
            // or cashbook entries change. Indexed for efficient filter & sort.
            $table->decimal('balance', 12, 2)->default(0)->after('rent_amount');
            $table->index('balance');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['balance']);
            $table->dropColumn('balance');
        });
    }
};
