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
        Schema::create('unit_task_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->text('feedback')->nullable();
            $table->string('event')->nullable();
            $table->string('notify')->nullable();

            $table->json('attachment_names')->nullable();

            $table->string('created_by_name')->nullable();
            $table->uuid('user_id')->nullable();
            $table->foreignUuid('unit_task_id')->constrained('unit_tasks')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('unit_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_task_updates');
    }
};
