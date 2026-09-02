<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carry an optional due date onto a journal line. A customer-control debit posted
 * from an invoice stores the invoice due_date here so the balance-forward Age
 * Analysis can bucket arrears from the GL alone (no invoice-table read).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
