<?php

namespace App\Services;

use App\Helpers\CountryHelper;
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
    public function getDashboardSummary(?string $country = null): array
    {
        $user     = Auth::user();
        $tenantId = $user->organization_id;

        // ------- Summary cards — single round trip via subqueries -------

        if ($country) {
            // Country-filtered variant: JOIN estates to scope by country
            $summary = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM estates WHERE organization_id = :tid1 AND country = :c1) AS total_estates,
                    (SELECT COUNT(*) FROM units JOIN estates ON estates.id = units.estate_id WHERE units.organization_id = :tid2 AND estates.country = :c2) AS total_units,
                    (SELECT COUNT(*) FROM units JOIN estates ON estates.id = units.estate_id WHERE units.organization_id = :tid3 AND estates.country = :c3 AND units.occupancy_type IN ('owner_occupied','tenant_occupied')) AS occupied_units,
                    (SELECT COUNT(*) FROM units JOIN estates ON estates.id = units.estate_id WHERE units.organization_id = :tid4 AND estates.country = :c4 AND units.occupancy_type = 'vacant') AS vacant_units,
                    (SELECT COALESCE(SUM(invoices.amount),0) FROM invoices JOIN units ON units.id = invoices.unit_id JOIN estates ON estates.id = units.estate_id WHERE invoices.organization_id = :tid5 AND estates.country = :c5 AND invoices.status IN (:s1,:s2,:s3)) AS total_outstanding,
                    (SELECT COUNT(*) FROM invoices JOIN units ON units.id = invoices.unit_id JOIN estates ON estates.id = units.estate_id WHERE invoices.organization_id = :tid5b AND estates.country = :c5b AND invoices.status IN (:s1b,:s2b,:s3b)) AS unpaid_invoices_count,
                    (SELECT COALESCE(SUM(cashbook_entries.amount),0) FROM cashbook_entries JOIN estates ON estates.id = cashbook_entries.estate_id WHERE cashbook_entries.organization_id = :tid6 AND estates.country = :c6 AND cashbook_entries.type = :ctype AND cashbook_entries.date >= :mstart AND cashbook_entries.date < :mend) AS collected_this_month,
                    (SELECT COUNT(*) FROM cashbook_entries JOIN estates ON estates.id = cashbook_entries.estate_id WHERE cashbook_entries.organization_id = :tid6b AND estates.country = :c6b AND cashbook_entries.type = :ctype2 AND cashbook_entries.date >= :mstart2 AND cashbook_entries.date < :mend2) AS payments_this_month_count,
                    (SELECT COUNT(*) FROM cashbook_entries JOIN estates ON estates.id = cashbook_entries.estate_id WHERE cashbook_entries.organization_id = :tid7 AND estates.country = :c7) AS total_cashbook_entries
            ", [
                'tid1'  => $tenantId, 'c1'  => $country,
                'tid2'  => $tenantId, 'c2'  => $country,
                'tid3'  => $tenantId, 'c3'  => $country,
                'tid4'  => $tenantId, 'c4'  => $country,
                'tid5'  => $tenantId, 'c5'  => $country,
                's1'    => InvoiceStatus::UNPAID->value,
                's2'    => InvoiceStatus::OVERDUE->value,
                's3'    => InvoiceStatus::PARTIALLY_PAID->value,
                'tid5b' => $tenantId, 'c5b' => $country,
                's1b'   => InvoiceStatus::UNPAID->value,
                's2b'   => InvoiceStatus::OVERDUE->value,
                's3b'   => InvoiceStatus::PARTIALLY_PAID->value,
                'tid6'   => $tenantId, 'c6'  => $country,
                'ctype'  => CashbookEntryType::CREDIT->value,
                'mstart' => now()->startOfMonth()->toDateString(),
                'mend'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid6b'  => $tenantId, 'c6b' => $country,
                'ctype2' => CashbookEntryType::CREDIT->value,
                'mstart2' => now()->startOfMonth()->toDateString(),
                'mend2'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid7'  => $tenantId, 'c7'  => $country,
            ]);
        } else {
            // Unfiltered variant: original query
            $summary = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM estates  WHERE organization_id = :tid1) AS total_estates,
                    (SELECT COUNT(*) FROM units    WHERE organization_id = :tid2) AS total_units,
                    (SELECT COUNT(*) FROM units    WHERE organization_id = :tid3 AND occupancy_type IN ('owner_occupied','tenant_occupied')) AS occupied_units,
                    (SELECT COUNT(*) FROM units    WHERE organization_id = :tid4 AND occupancy_type = 'vacant') AS vacant_units,
                    (SELECT COALESCE(SUM(amount),0) FROM invoices WHERE organization_id = :tid5 AND status IN (:s1,:s2,:s3)) AS total_outstanding,
                    (SELECT COUNT(*) FROM invoices WHERE organization_id = :tid5b AND status IN (:s1b,:s2b,:s3b)) AS unpaid_invoices_count,
                    (SELECT COALESCE(SUM(amount),0) FROM cashbook_entries WHERE organization_id = :tid6 AND type = :ctype AND date >= :mstart AND date < :mend) AS collected_this_month,
                    (SELECT COUNT(*) FROM cashbook_entries WHERE organization_id = :tid6b AND type = :ctype2 AND date >= :mstart2 AND date < :mend2) AS payments_this_month_count,
                    (SELECT COUNT(*) FROM cashbook_entries WHERE organization_id = :tid7) AS total_cashbook_entries
            ", [
                'tid1'  => $tenantId,
                'tid2'  => $tenantId,
                'tid3'  => $tenantId,
                'tid4'  => $tenantId,
                'tid5'  => $tenantId,
                's1'    => InvoiceStatus::UNPAID->value,
                's2'    => InvoiceStatus::OVERDUE->value,
                's3'    => InvoiceStatus::PARTIALLY_PAID->value,
                'tid5b' => $tenantId,
                's1b'   => InvoiceStatus::UNPAID->value,
                's2b'   => InvoiceStatus::OVERDUE->value,
                's3b'   => InvoiceStatus::PARTIALLY_PAID->value,
                'tid6'   => $tenantId,
                'ctype'  => CashbookEntryType::CREDIT->value,
                'mstart' => now()->startOfMonth()->toDateString(),
                'mend'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid6b'  => $tenantId,
                'ctype2' => CashbookEntryType::CREDIT->value,
                'mstart2' => now()->startOfMonth()->toDateString(),
                'mend2'   => now()->addMonth()->startOfMonth()->toDateString(),
                'tid7'  => $tenantId,
            ]);
        }

        $totalUnits    = (int) $summary->total_units;
        $occupiedUnits = (int) $summary->occupied_units;
        $occupancyRate = $totalUnits > 0
            ? round(($occupiedUnits / $totalUnits) * 100, 1)
            : 0;

        // ------- Recent invoices — single query with joins -------

        $recentInvoices = Invoice::where('invoices.organization_id', $tenantId)
            ->when($country, fn($q) => $q->whereHas('unit.estate', fn($eq) => $eq->where('country', $country)))
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

        $estatesOverview = Estate::where('organization_id', $tenantId)
            ->when($country, fn($q) => $q->where('country', $country))
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
                'country'               => $estate->country,
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
                'unpaid_invoices_count' => (int) $summary->unpaid_invoices_count,
                'collected_this_month' => (float) $summary->collected_this_month,
                'payments_this_month_count' => (int) $summary->payments_this_month_count,
                'occupied_units'       => $occupiedUnits,
                'vacant_units'         => (int) $summary->vacant_units,
                'occupancy_rate'       => $occupancyRate,
                'total_cashbook_entries' => (int) $summary->total_cashbook_entries,
            ],
            'recent_invoices'  => $recentInvoices,
            'estates_overview' => $estatesOverview,
            'currency'         => $country ? CountryHelper::get($country) : null,
        ];
    }
}
