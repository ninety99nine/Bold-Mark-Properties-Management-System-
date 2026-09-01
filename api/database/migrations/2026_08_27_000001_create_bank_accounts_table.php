<?php

use App\Enums\BankAccountType;
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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->enum('type', BankAccountType::values())->default(BankAccountType::CURRENT->value);
            $table->decimal('balance', 15, 2)->default(0);
            $table->date('balance_as_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();

            $table->timestamps();

            $table->index('community_id');
            $table->index('organization_id');
            $table->index(['community_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
