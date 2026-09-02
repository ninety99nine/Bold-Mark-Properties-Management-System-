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
        Schema::create('unit_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('original_name')->nullable();
            $table->string('file_path');
            $table->string('mime_type')->nullable();

            $table->unsignedInteger('size')->default(0);

            $table->string('uploaded_by_name')->nullable();
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
        Schema::dropIfExists('unit_documents');
    }
};
