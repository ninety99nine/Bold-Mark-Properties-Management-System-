<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A journal batch is a set of balanced double-entry lines, exactly like
 * WeConnectU's Journals page. Batches are scoped to a community and a financial
 * year / budget period, carry an optional Journal Group tag, and can have
 * supporting files attached. The per-community `batch_number` renders as the
 * batch name ("Journal Batch 38").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->unsignedInteger('batch_number');
            $table->string('journal_group')->nullable();
            $table->date('date');
            $table->unsignedSmallInteger('financial_year');

            $table->json('files')->nullable();

            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('community_id');
            $table->index(['community_id', 'financial_year']);
            $table->index(['community_id', 'date']);
            $table->unique(['community_id', 'batch_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_batches');
    }
};
