<?php

use App\Enums\CollectionStatus;
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
        Schema::table('units', function (Blueprint $table) {
            $table->enum('collection_status', CollectionStatus::values())
                ->default(CollectionStatus::NONE->value)
                ->after('is_development');

            $table->index('collection_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['collection_status']);
            $table->dropColumn('collection_status');
        });
    }
};
