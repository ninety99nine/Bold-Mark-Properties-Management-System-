<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Community-level tasks (WeConnectU parity): a task's "area" may be a unit,
 * common property or N/A — so unit_id becomes nullable. Also widen the status
 * enum to the WeConnectU set (In Progress / Pending Payment / On Hold / Complete).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Widen the status enum to the full WeConnectU set. MySQL enforces the enum
        // at the column level; SQLite stores it as a string, so the raw ALTER is
        // MySQL-only (and a no-op elsewhere — the new values just work as strings).
        if (DB::getDriverName() === 'mysql') {
            $values = "'" . implode("','", TaskStatus::values()) . "'";
            DB::statement("ALTER TABLE unit_tasks MODIFY COLUMN status ENUM($values) NOT NULL DEFAULT 'open'");
        }

        // Allow community-wide tasks (Common Property / Not Applicable) with no unit.
        Schema::table('unit_tasks', function ($table) {
            $table->uuid('unit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE unit_tasks MODIFY COLUMN status ENUM('open','in_progress','complete') NOT NULL DEFAULT 'open'");
        }
    }
};
