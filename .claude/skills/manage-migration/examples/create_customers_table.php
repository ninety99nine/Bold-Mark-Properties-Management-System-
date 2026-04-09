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
        Schema::create('customers', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('country', 2)->nullable();
            $table->boolean('is_active');

            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('total_completed_orders')->default(0);
            $table->unsignedInteger('total_cancelled_orders')->default(0);
            $table->unsignedInteger('total_mobile_orders')->default(0);
            $table->unsignedInteger('total_web_orders')->default(0);
            $table->unsignedInteger('total_steps')->default(0);
            $table->unsignedInteger('total_spend')->default(0);

            $table->timestamp('suspended_at')->nullable();
            $table->unsignedInteger('open_disputes_count')->default(0);

            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();

            $table->timestamps();

            $table->index('email');
            $table->index('phone');
            $table->index('country');
            $table->index('suspended_at');
            $table->index('open_disputes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
