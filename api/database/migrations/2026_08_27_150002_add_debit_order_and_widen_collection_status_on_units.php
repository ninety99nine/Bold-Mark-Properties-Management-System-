<?php

use App\Enums\CollectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a `debit_order` flag (WeConnectU "Debit Order Client" / "Debit Order
     * Customers" filter) and widens the collection_status enum to include the
     * WeConnectU "Payment Arrangement" status. The enum widening is MySQL-only
     * (SQLite stores enums as strings, so the new value already works).
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('debit_order')->default(false)->after('collection_status');
            $table->index('debit_order');
        });

        if (DB::getDriverName() === 'mysql') {
            $values = "'" . implode("','", CollectionStatus::values()) . "'";
            DB::statement("ALTER TABLE units MODIFY COLUMN collection_status ENUM($values) NOT NULL DEFAULT 'none'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['debit_order']);
            $table->dropColumn('debit_order');
        });
    }
};
