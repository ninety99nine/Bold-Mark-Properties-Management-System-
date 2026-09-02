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
        Schema::create('unit_communications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email');
            $table->string('bcc')->nullable();
            $table->string('sent_by_name')->nullable();
            $table->json('attachment_names')->nullable();
            $table->string('resend_email_id')->nullable();

            $table->uuid('user_id')->nullable();
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('unit_id');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_communications');
    }
};
