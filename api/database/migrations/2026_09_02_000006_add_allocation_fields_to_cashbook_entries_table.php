<?php

use App\Enums\JournalLineType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WeConnectU allocation model: a bank line is allocated to a ledger TYPE
 * (general / customer / supplier / reserve_fund) rather than to a specific
 * invoice. `allocation_ledger_type` records the chosen type and doubles as the
 * "is allocated" marker; the target account is stored in the existing
 * ledger_id / unit_id columns or the new supplier_id column. vat_type and
 * allocation_remarks capture the VAT treatment and free-text note.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->enum('allocation_ledger_type', JournalLineType::values())->nullable()->after('ledger_id');
            $table->string('vat_type')->nullable()->after('allocation_ledger_type');
            $table->string('allocation_remarks')->nullable()->after('vat_type');

            $table->foreignUuid('supplier_id')->nullable()->after('unit_id')->constrained('suppliers')->nullOnDelete();

            $table->index('allocation_ledger_type');
            $table->index('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['allocation_ledger_type', 'vat_type', 'allocation_remarks', 'supplier_id']);
        });
    }
};
