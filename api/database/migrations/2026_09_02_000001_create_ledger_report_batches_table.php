<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Recent Email Reports" for the Detailed Customer Ledger (WeConnectU parity):
 * each row records a requested/emailed ledger report — its date range, how many
 * customer accounts it covered and when it was generated. Clicking the row's
 * date range re-downloads that report's Excel workbook.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_report_batches', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->date('date_from');
            $table->date('date_to');
            $table->string('status')->default('completed');

            $table->unsignedInteger('account_count')->default(0);

            $table->timestamp('generated_at')->nullable();

            $table->json('filters')->nullable();

            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('community_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_report_batches');
    }
};
