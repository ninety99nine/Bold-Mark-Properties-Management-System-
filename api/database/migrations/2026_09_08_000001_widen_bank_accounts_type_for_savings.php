<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relax bank_accounts.type from a two-value enum (current/investment) to a plain
 * string so WeConnectU's third cashbook account type — "Savings" — is accepted,
 * and future types can be added without a schema change. Values stay validated
 * at the application layer by App\Enums\BankAccountType.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('type', 20)->default('current')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->enum('type', ['current', 'investment'])->default('current')->change();
        });
    }
};
