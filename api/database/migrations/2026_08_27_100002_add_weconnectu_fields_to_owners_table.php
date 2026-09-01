<?php

use App\Enums\OwnerEntityType;
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
        Schema::table('owners', function (Blueprint $table) {
            $table->string('landline', 30)->nullable()->after('phone');
            $table->enum('entity_type', OwnerEntityType::values())->nullable()->after('id_number');

            $table->string('contact2_name')->nullable()->after('entity_type');
            $table->string('contact2_email')->nullable()->after('contact2_name');
            $table->string('contact2_phone', 30)->nullable()->after('contact2_email');
            $table->string('contact2_landline', 30)->nullable()->after('contact2_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn([
                'landline',
                'entity_type',
                'contact2_name',
                'contact2_email',
                'contact2_phone',
                'contact2_landline',
            ]);
        });
    }
};
