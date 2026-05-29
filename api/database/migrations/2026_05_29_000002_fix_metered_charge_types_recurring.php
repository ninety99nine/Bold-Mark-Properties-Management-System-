<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Metered utilities have variable amounts per reading — not fixed monthly configs
        DB::table('charge_types')
            ->whereIn('name', ['Water Recovery', 'Electricity Recovery', 'Gas Recovery'])
            ->update(['is_recurring' => false]);
    }

    public function down(): void
    {
        DB::table('charge_types')
            ->whereIn('name', ['Water Recovery', 'Electricity Recovery', 'Gas Recovery'])
            ->update(['is_recurring' => true]);
    }
};
