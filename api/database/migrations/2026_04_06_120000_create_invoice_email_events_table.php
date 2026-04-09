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
        Schema::create('invoice_email_events', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('event_type', 20);
            $table->string('email')->nullable();
            $table->string('resend_email_id')->nullable();
            $table->timestamp('occurred_at');

            $table->json('metadata')->nullable();

            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['invoice_id', 'event_type']);
            $table->index('tenant_id');
            $table->index('resend_email_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_email_events');
    }
};
