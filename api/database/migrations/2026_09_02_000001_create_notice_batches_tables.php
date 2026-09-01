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
        Schema::create('notice_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->date('ageing_date');
            $table->unsignedInteger('total')->default(0);
            $table->string('created_by_name')->nullable();

            $table->uuid('user_id')->nullable();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('community_id');
            $table->index('organization_id');
        });

        Schema::create('notice_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('customer_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('level')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('pdf_path')->nullable();
            $table->boolean('emailed')->default(false);
            $table->string('email')->nullable();

            $table->uuid('owner_id')->nullable();
            $table->foreignUuid('notice_batch_id')->constrained('notice_batches')->cascadeOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('notice_batch_id');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notice_batch_items');
        Schema::dropIfExists('notice_batches');
    }
};
