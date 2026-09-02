<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers (creditors) mirror WeConnectU's supplier ledger. A supplier can be
 * community-specific or shared across the organisation (community_id null). The
 * per-community `supplier_code` (e.g. "ACC001") renders in the allocation picker
 * as "ACC001 - Access And Perimeter Security Pty Ltd".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('supplier_code', 50);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();

            $table->string('bank_name')->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('registration_number', 50)->nullable();
            $table->text('address')->nullable();

            $table->boolean('is_active')->default(true);

            $table->decimal('balance', 15, 2)->default(0);

            $table->foreignUuid('community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('community_id');
            $table->index('supplier_code');
            $table->index(['organization_id', 'community_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
