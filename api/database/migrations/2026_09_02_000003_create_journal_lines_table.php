<?php

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single line of a journal batch. The `line_type` decides which account
 * column is populated:
 *   general / reserve_fund → ledger_id  (a chart-of-accounts ledger)
 *   customer               → unit_id    (the customer's unit / account)
 *   supplier               → (none yet — suppliers are not modelled)
 *
 * `amount` is always stored positive; `entry_type` records debit vs credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->enum('line_type', JournalLineType::values());
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('entry_type', JournalEntryType::values());
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignUuid('journal_batch_id')->constrained('journal_batches')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();

            $table->timestamps();

            $table->index('journal_batch_id');
            $table->index('organization_id');
            $table->index('ledger_id');
            $table->index('unit_id');
            $table->index(['unit_id', 'line_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
