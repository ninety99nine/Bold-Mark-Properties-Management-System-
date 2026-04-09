<?php

use App\Enums\CashbookEntryType;
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
        Schema::create('cashbook_entries', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('description');
            $table->decimal('amount', 12, 2);

            $table->enum('type', CashbookEntryType::values());

            $table->date('date');
            $table->text('notes')->nullable();

            // parent_entry_id is nullable UUID — the self-referential FK is added below
            // after the table exists, because PostgreSQL cannot verify the constraint inline.
            $table->uuid('parent_entry_id')->nullable();

            $table->foreignUuid('estate_id')->constrained('estates')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('charge_type_id')->nullable()->constrained('charge_types')->nullOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->timestamps();

            $table->index('estate_id');
            $table->index('tenant_id');
            $table->index('date');
            $table->index('type');
            $table->index('unit_id');
            $table->index('invoice_id');
            $table->index('charge_type_id');
            $table->index('parent_entry_id');
            $table->index(['estate_id', 'date']);
            $table->index(['unit_id', 'invoice_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['estate_id', 'type', 'date']);
        });

        // Add the self-referential FK after the table is fully created.
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->foreign('parent_entry_id')
                  ->references('id')
                  ->on('cashbook_entries')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->dropForeign(['parent_entry_id']);
        });
        Schema::dropIfExists('cashbook_entries');
    }
};
