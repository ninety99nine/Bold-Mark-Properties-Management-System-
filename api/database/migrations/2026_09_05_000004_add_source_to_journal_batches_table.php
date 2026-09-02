<?php

use App\Enums\JournalSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag every journal batch with its origin. Manual journals keep a per-community
 * batch_number and show on the Journals page; auto-postings (invoices, credit
 * notes, cashbook allocations, supplier invoices, opening balances) carry a
 * source + polymorphic source_type/source_id back-reference to their document
 * and have a NULL batch_number (they never consume a manual number). All reports
 * read every source; the Journals list filters to source='manual'.
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
        Schema::table('journal_batches', function (Blueprint $table) {
            $table->enum('source', JournalSource::values())
                  ->default(JournalSource::MANUAL->value)
                  ->after('id');
            $table->string('source_type')->nullable()->after('source');
            $table->uuid('source_id')->nullable()->after('source_type');

            $table->unsignedInteger('batch_number')->nullable()->change();

            $table->index(['community_id', 'source']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('journal_batches', function (Blueprint $table) {
            $table->dropIndex(['community_id', 'source']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source', 'source_type', 'source_id']);
            $table->unsignedInteger('batch_number')->nullable(false)->change();
        });
    }
};
