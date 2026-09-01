<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient delivery rows for a communication. Carries the Resend email
 * id (for webhook status updates: sent → delivered → read) and an unguessable
 * view_token that powers the public "Download" email-view page
 * (WeConnectU download-mail-new.php parity).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('communication_recipients', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('recipient_name')->nullable();
            $table->string('recipient_email');
            $table->string('status')->default('queued');
            $table->text('error')->nullable();

            $table->string('resend_email_id')->nullable();
            $table->uuid('view_token');

            $table->foreignUuid('communication_id')->constrained('communications')->cascadeOnDelete();

            $table->timestamps();

            $table->index('resend_email_id');
            $table->index('view_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_recipients');
    }
};
