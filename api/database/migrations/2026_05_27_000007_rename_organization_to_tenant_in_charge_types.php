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
        // Step 1: expand enum to allow both old and new value
        DB::statement("ALTER TABLE charge_types MODIFY COLUMN applies_to ENUM('owner', 'organization', 'tenant', 'either') NOT NULL DEFAULT 'either'");

        // Step 2: migrate existing data
        DB::statement("UPDATE charge_types SET applies_to = 'tenant' WHERE applies_to = 'organization'");

        // Step 3: drop the old value from the enum
        DB::statement("ALTER TABLE charge_types MODIFY COLUMN applies_to ENUM('owner', 'tenant', 'either') NOT NULL DEFAULT 'either'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE charge_types SET applies_to = 'organization' WHERE applies_to = 'tenant'");
        DB::statement("ALTER TABLE charge_types MODIFY COLUMN applies_to ENUM('owner', 'organization', 'either') NOT NULL DEFAULT 'either'");
    }
};
