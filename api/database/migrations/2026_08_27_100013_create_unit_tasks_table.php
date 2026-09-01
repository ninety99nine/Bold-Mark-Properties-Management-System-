<?php

use App\Enums\TaskStatus;
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
        Schema::create('unit_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('category')->nullable();
            $table->string('task_type')->nullable();
            $table->string('area')->nullable();
            $table->string('recurring_type')->nullable();
            $table->string('assignee_name')->nullable();

            $table->enum('status', TaskStatus::values())->default(TaskStatus::OPEN->value);

            $table->boolean('internal')->default(false);

            $table->date('due_date')->nullable();

            $table->json('contacts')->nullable();
            $table->json('supplier_names')->nullable();
            $table->json('attachment_names')->nullable();

            $table->string('created_by_name')->nullable();
            $table->uuid('assignee_user_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('unit_id');
            $table->index('community_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_tasks');
    }
};
