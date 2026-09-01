<?php

use App\Enums\CommunicationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sent communication batch backing the Communicate → Archive tab
 * (WeConnectU "Communication Archive"). One row per send; per-recipient
 * delivery is tracked in communication_recipients. community_id is nullable
 * to support portfolio-wide sends from the global Communicate page.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('bcc')->nullable();

            $table->enum('type', CommunicationType::values())->default(CommunicationType::MAIL->value);
            $table->string('status')->default('queued');

            $table->string('sent_by_name')->nullable();

            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            $table->json('recipient_groups')->nullable();
            $table->json('attachment_names')->nullable();

            $table->foreignUuid('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('community_id');
            $table->index('organization_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
