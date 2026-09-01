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
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'code']);
            $table->dropColumn('code');
            $table->string('type', 50)->nullable()->after('id');

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
            $table->string('code', 50)->default('');
            $table->unique(['organization_id', 'code']);
        });
    }
};
