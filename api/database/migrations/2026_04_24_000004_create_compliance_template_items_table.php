<?php

use App\Enums\ComplianceItemPriority;
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
        Schema::create('compliance_template_items', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category', 50);
            $table->text('description')->nullable();

            $table->enum('priority', ComplianceItemPriority::values())->default(ComplianceItemPriority::MEDIUM->value);

            $table->unsignedInteger('default_month_due')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_recurring')->default(true);

            $table->foreignUuid('compliance_template_id')->constrained('compliance_templates')->cascadeOnDelete();

            $table->timestamps();

            $table->index('compliance_template_id', 'template_items_template_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_template_items');
    }
};
