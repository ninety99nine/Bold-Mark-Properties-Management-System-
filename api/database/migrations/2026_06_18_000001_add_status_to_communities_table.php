<?php

use App\Enums\CommunityStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->enum('status', CommunityStatus::values())
                ->default(CommunityStatus::ACTIVE->value)
                ->after('is_active');

            $table->index('status');
            $table->index(['organization_id', 'status']);
        });

        // Backfill from the existing is_active flag: inactive communities become
        // "suspended"; everything else stays "active".
        DB::table('communities')
            ->where('is_active', false)
            ->update(['status' => CommunityStatus::SUSPENDED->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
