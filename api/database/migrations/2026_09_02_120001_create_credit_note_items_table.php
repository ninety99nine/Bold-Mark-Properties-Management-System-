<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('amount', 12, 2)->default(0);       // net unit amount
            $table->decimal('tax_rate', 5, 2)->default(0);      // VAT %
            $table->decimal('tax_amount', 12, 2)->default(0);   // computed VAT
            $table->decimal('line_total', 12, 2)->default(0);   // quantity * amount + tax_amount
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignUuid('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignUuid('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();

            $table->timestamps();

            $table->index('credit_note_id');
            $table->index('ledger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
