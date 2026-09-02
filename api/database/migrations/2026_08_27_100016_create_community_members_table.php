<?php

use App\Enums\CommunityMemberType;
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
        Schema::create('community_members', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('cellphone', 30)->nullable();

            $table->enum('user_type', CommunityMemberType::values())->default(CommunityMemberType::OWNER->value);

            $table->boolean('is_director_trustee')->default(false);
            $table->boolean('is_payment_authoriser')->default(false);
            $table->boolean('is_verified')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->uuid('user_id')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('community_id');
            $table->index('organization_id');
            $table->index('user_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_members');
    }
};
