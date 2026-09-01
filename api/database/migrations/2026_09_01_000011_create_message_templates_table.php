<?php

use App\Enums\MessageTemplateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization-scoped message templates backing the global
 * Settings → Communication template manager (WeConnectU "Message Setup").
 * Each row is one customisable template (arrears notices, letters of demand,
 * handover, fine/penalty/warning and their SMS variants) keyed by `key`,
 * seeded from canonical defaults and restorable via "Reset to default".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('key');
            $table->string('name');
            $table->enum('type', MessageTemplateType::values())->default(MessageTemplateType::EMAIL->value);

            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->longText('terms')->nullable();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['organization_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
