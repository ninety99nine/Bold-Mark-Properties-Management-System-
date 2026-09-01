<?php

use App\Enums\BilledToType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('credit_note_number', 50)->unique();

            $table->enum('billed_to_type', BilledToType::values());
            $table->uuid('billed_to_id');

            $table->string('reason')->nullable();
            $table->string('order_no', 100)->nullable();
            $table->string('reference', 100)->nullable();

            $table->decimal('amount', 12, 2);
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('vat_amount', 12, 2)->nullable();
            $table->date('credit_note_date');

            $table->timestamp('sent_at')->nullable();
            $table->string('issued_by_type')->nullable();
            $table->uuid('issued_by_user_id')->nullable();

            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('applied_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
            $table->index('unit_id');
            $table->index('applied_invoice_id');
            $table->index('billed_to_type');
            $table->index('billed_to_id');
            $table->index('credit_note_date');
            $table->index(['billed_to_type', 'billed_to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
