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
            DB::statement("ALTER TABLE charge_types MODIFY COLUMN applies_to ENUM('owner', 'organization', 'tenant', 'either') NOT NULL DEFAULT 'either'");
        }

        DB::statement("UPDATE charge_types SET applies_to = 'tenant' WHERE applies_to = 'organization'");

        if ($isMySQL) {
            DB::statement("ALTER TABLE charge_types MODIFY COLUMN applies_to ENUM('owner', 'tenant', 'either') NOT NULL DEFAULT 'either'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE charge_types SET applies_to = 'organization' WHERE applies_to = 'tenant'");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE charge_types MODIFY COLUMN applies_to ENUM('owner', 'organization', 'either') NOT NULL DEFAULT 'either'");
        }
    }
};
