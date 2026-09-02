<?php

use App\Enums\ComplianceItemPriority;
use App\Enums\ComplianceItemStatus;
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
        Schema::create('compliance_checklist_items', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category', 50);
            $table->text('description')->nullable();

            $table->enum('status', ComplianceItemStatus::values())->default(ComplianceItemStatus::PENDING->value);
            $table->enum('priority', ComplianceItemPriority::values())->default(ComplianceItemPriority::MEDIUM->value);

            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_recurring')->default(true);

            $table->text('completion_notes')->nullable();
            $table->string('evidence_file_path')->nullable();
            $table->string('evidence_file_name')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignUuid('compliance_checklist_id')->constrained('compliance_checklists')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('compliance_checklist_id', 'compliance_items_checklist_idx');
            $table->index('organization_id', 'compliance_items_organization_idx');
            $table->index('status', 'compliance_items_status_idx');
            $table->index('category', 'compliance_items_category_idx');
            $table->index('due_date', 'compliance_items_due_date_idx');
            $table->index(['compliance_checklist_id', 'status'], 'compliance_items_checklist_status_idx');
            $table->index(['compliance_checklist_id', 'category'], 'compliance_items_checklist_category_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_checklist_items');
    }
};
