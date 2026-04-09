<?php

namespace App\Services;

use App\Models\CashbookEntry;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Unit;
use App\Enums\CashbookEntryType;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Return all summary data needed to render the dashboard.
     *
     * @return array
     */
    public function getDashboardSummary(): array
    {
        $user     = Auth::user();
        $tenantId = $user->tenant_id;

        // ------- Summary cards — single round trip via subqueries -------

        $summary = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM estates  WHERE tenant_id = :tid1) AS total_estates,
                (SELECT COUNT(*) FROM units    WHERE tenant_id = :tid2) AS total_units,
                (SELECT COUNT(*) FROM units    WHERE tenant_id = :tid3 AND occupancy_type IN ('owner_occupied','tenant_occupied')) AS occupied_units,
                (SELECT COUNT(*) FROM units    WHERE tenant_id = :tid4 AND occupancy_type = 'vacant') AS vacant_units,
                (SELECT COALESCE(SUM(amount),0) FROM invoices WHERE tenant_id = :tid5 AND status IN (:s1,:s2,:s3)) AS total_outstanding,
                (SELECT COALESCE(SUM(amount),0) FROM cashbook_entries WHERE tenant_id = :tid6 AND type = :ctype AND EXTRACT(MONTH FROM date) = :month AND EXTRACT(YEAR FROM date) = :year) AS collected_this_month
        ", [
            'tid1'  => $tenantId,
            'tid2'  => $tenantId,
            'tid3'  => $tenantId,
            'tid4'  => $tenantId,
            'tid5'  => $tenantId,
            's1'    => InvoiceStatus::UNPAID->value,
            's2'    => InvoiceStatus::OVERDUE->value,
            's3'    => InvoiceStatus::PARTIALLY_PAID->value,
            'tid6'  => $tenantId,
            'ctype' => CashbookEntryType::CREDIT->value,
            'month' => now()->month,
            'year'  => now()->year,
        ]);

        $totalUnits    = (int) $summary->total_units;
        $occupiedUnits = (int) $summary->occupied_units;
        $occupancyRate = $totalUnits > 0
            ? round(($occupiedUnits / $totalUnits) * 100, 1)
            : 0;

        // ------- Recent invoices — single query with joins -------

        $recentInvoices = Invoice::where('invoices.tenant_id', $tenantId)
            ->with(['unit', 'chargeType', 'billedToOwner', 'billedToUnitTenant'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($invoice) => [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status'         => $invoice->status instanceof InvoiceStatus
                    ? $invoice->status->value
                    : $invoice->status,
                'amount'         => $invoice->amount,
                'charge_type'    => $invoice->chargeType?->name,
                'unit_number'    => $invoice->unit?->unit_number,
                'billing_period' => $invoice->billing_period?->format('Y-m'),
                'due_date'       => $invoice->due_date?->format('Y-m-d'),
                'billed_to_name' => match (true) {
                    $invoice->billed_to_type === \App\Enums\BilledToType::OWNER  => $invoice->billedToOwner?->full_name,
                    $invoice->billed_to_type === \App\Enums\BilledToType::TENANT => $invoice->billedToUnitTenant?->full_name,
                    default => null,
                },
            ]);

        // ------- Estates overview — single query with conditional counts -------

        $estatesOverview = Estate::where('tenant_id', $tenantId)
            ->withCount([
                'units',
                'units as owner_occupied_count' => fn($q) => $q->where('occupancy_type', 'owner_occupied'),
                'units as tenant_occupied_count' => fn($q) => $q->where('occupancy_type', 'tenant_occupied'),
                'units as vacant_count'          => fn($q) => $q->where('occupancy_type', 'vacant'),
            ])
            ->get()
            ->map(fn($estate) => [
                'id'                    => $estate->id,
                'name'                  => $estate->name,
                'type'                  => $estate->type instanceof \App\Enums\EstateType
                    ? $estate->type->value
                    : $estate->type,
                'units_count'           => $estate->units_count,
                'owner_occupied_count'  => $estate->owner_occupied_count,
                'tenant_occupied_count' => $estate->tenant_occupied_count,
                'vacant_count'          => $estate->vacant_count,
            ]);

        return [
            'summary' => [
                'total_estates'        => (int) $summary->total_estates,
                'total_units'          => $totalUnits,
                'total_outstanding'    => (float) $summary->total_outstanding,
                'collected_this_month' => (float) $summary->collected_this_month,
                'occupied_units'       => $occupiedUnits,
                'vacant_units'         => (int) $summary->vacant_units,
                'occupancy_rate'       => $occupancyRate,
            ],
            'recent_invoices'  => $recentInvoices,
            'estates_overview' => $estatesOverview,
        ];
    }
}
