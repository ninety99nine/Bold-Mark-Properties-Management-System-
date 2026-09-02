<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('supplier_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');

            $table->foreignUuid('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('community_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_groups');
    }
};
