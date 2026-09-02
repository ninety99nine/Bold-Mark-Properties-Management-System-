<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isMySQL = DB::getDriverName() === 'mysql';

        if ($isMySQL) {
            DB::statement("ALTER TABLE ledgers MODIFY COLUMN applies_to ENUM('owner', 'organization', 'occupant', 'either') NOT NULL DEFAULT 'either'");
        }

        DB::statement("UPDATE ledgers SET applies_to = 'occupant' WHERE applies_to = 'organization'");

        if ($isMySQL) {
            DB::statement("ALTER TABLE ledgers MODIFY COLUMN applies_to ENUM('owner', 'occupant', 'either') NOT NULL DEFAULT 'either'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE ledgers SET applies_to = 'organization' WHERE applies_to = 'occupant'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ledgers MODIFY COLUMN applies_to ENUM('owner', 'organization', 'either') NOT NULL DEFAULT 'either'");
        }
    }
};
