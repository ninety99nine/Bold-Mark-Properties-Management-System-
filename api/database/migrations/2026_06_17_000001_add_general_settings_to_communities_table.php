<?php

use App\Enums\AgeingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name');
            $table->unsignedTinyInteger('financial_year_end_month')->nullable()->after('entity_type');

            $table->boolean('is_vat_registered')->default(false)->after('income_tax_number');
            $table->string('vat_number', 100)->nullable()->after('is_vat_registered');

            $table->decimal('interest_rate', 5, 2)->nullable()->after('vat_number');
            $table->decimal('interest_exempt_threshold', 12, 2)->nullable()->after('interest_rate');
            $table->enum('ageing_type', AgeingType::values())->default(AgeingType::CALENDAR_MONTH->value)->after('interest_exempt_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'financial_year_end_month',
                'is_vat_registered',
                'vat_number',
                'interest_rate',
                'interest_exempt_threshold',
                'ageing_type',
            ]);
        });
    }
};
