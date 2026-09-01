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
        Schema::create('community_budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Financial year the budget applies to (e.g. 2026).
            $table->smallInteger('year');

            // WeConnectU budget = one amount per ledger account, per calendar month.
            $table->decimal('jan', 14, 2)->default(0);
            $table->decimal('feb', 14, 2)->default(0);
            $table->decimal('mar', 14, 2)->default(0);
            $table->decimal('apr', 14, 2)->default(0);
            $table->decimal('may', 14, 2)->default(0);
            $table->decimal('jun', 14, 2)->default(0);
            $table->decimal('jul', 14, 2)->default(0);
            $table->decimal('aug', 14, 2)->default(0);
            $table->decimal('sep', 14, 2)->default(0);
            $table->decimal('oct', 14, 2)->default(0);
            $table->decimal('nov', 14, 2)->default(0);
            $table->decimal('dec', 14, 2)->default(0);
            $table->decimal('per_year', 14, 2)->default(0);

            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('ledger_id')->constrained('ledgers')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['community_id', 'ledger_id', 'year']);
            $table->index('community_id');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_budgets');
    }
};
