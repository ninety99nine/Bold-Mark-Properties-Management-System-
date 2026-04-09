<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved table views — user-defined combinations of date range, filters, and sort
     * that can be applied to any data table in the platform.
     *
     * The `context` column identifies which table this view belongs to
     * (e.g. 'units', 'invoices', 'cashbook', 'age-analysis', 'users').
     *
     * Views are scoped per user per context and are never shared across users.
     * Filters are stored as a JSON blob because each context has different filterable fields.
     */
    public function up(): void
    {
        Schema::create('table_views', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Identifies which table this view belongs to.
            // Allowed values: units, invoices, cashbook, age-analysis, users
            $table->string('context', 60);

            $table->string('name', 100);

            // Date range preset. Null / 'all_time' = no date filter applied.
            $table->string('date_range', 20)->default('all_time');

            // Only populated when date_range = 'custom'
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();

            // Context-specific filter values, e.g.:
            //   units:    {"occupancy_type": "tenant_occupied", "balance": "in_arrears"}
            //   invoices: {"status": "overdue", "charge_type_id": "uuid"}
            //   cashbook: {"allocation_status": "unallocated", "type": "credit"}
            $table->json('filters')->nullable();

            // Sort field and direction, e.g. field = 'unit_number', direction = 'asc'
            $table->string('sort_field', 60)->nullable();
            $table->string('sort_direction', 4)->default('asc');

            $table->timestamps();

            // Look up all views for a user in a given context
            $table->index(['user_id', 'context'], 'table_views_user_context_idx');

            // Tenant-level index for data isolation checks
            $table->index(['tenant_id', 'context'], 'table_views_tenant_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_views');
    }
};
