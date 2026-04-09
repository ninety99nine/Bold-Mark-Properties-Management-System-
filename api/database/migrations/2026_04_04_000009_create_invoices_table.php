<?php

use App\Enums\BilledToType;
use App\Enums\InvoiceStatus;
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
        Schema::create('invoices', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('invoice_number', 50)->unique();

            $table->enum('status', InvoiceStatus::values())->default(InvoiceStatus::UNPAID->value);
            $table->enum('billed_to_type', BilledToType::values());

            $table->decimal('amount', 12, 2);
            $table->date('billing_period');
            $table->date('due_date');

            $table->uuid('billed_to_id');
            $table->timestamp('sent_at')->nullable();

            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('charge_type_id')->constrained('charge_types')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['unit_id', 'charge_type_id', 'billing_period']);
            $table->index('tenant_id');
            $table->index('unit_id');
            $table->index('charge_type_id');
            $table->index('status');
            $table->index('billing_period');
            $table->index('due_date');
            $table->index('billed_to_type');
            $table->index('billed_to_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['unit_id', 'billing_period']);
            $table->index(['billed_to_type', 'billed_to_id']);
            $table->index(['tenant_id', 'billing_period', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
