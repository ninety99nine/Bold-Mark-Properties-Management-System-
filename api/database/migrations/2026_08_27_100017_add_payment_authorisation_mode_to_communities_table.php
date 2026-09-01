<?php

use App\Enums\PaymentAuthorisationMode;
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
        Schema::table('communities', function (Blueprint $table) {
            $table->enum('payment_authorisation_mode', PaymentAuthorisationMode::values())
                ->default(PaymentAuthorisationMode::SINGLE->value)
                ->after('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn('payment_authorisation_mode');
        });
    }
};
