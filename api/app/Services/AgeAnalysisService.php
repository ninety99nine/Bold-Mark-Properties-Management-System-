<?php

namespace App\Services;

use App\Models\Invoice;
use App\Enums\BilledToType;
use App\Enums\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AgeAnalysisService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Compute the age analysis report for the authenticated tenant.
     *
     * Ageing buckets (based on due_date):
     *   current    → due_date >= today (not yet late)
     *   30_days    → due 1–30 days ago
     *   60_days    → due 31–60 days ago
     *   90_days    → due 61–90 days ago
     *   120_plus   → due more than 90 days ago
     *
     * @param array $data  Optional filters: estate_id, charge_type_id, billed_to_type
     * @return array
     */
    public function getAgeAnalysis(array $data): array
    {
        $user     = Auth::user();
        $tenantId = $user->tenant_id;
        $today    = Carbon::today();

        // Only include invoices that have an outstanding balance
        $query = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', [
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PARTIALLY_PAID->value,
            ])
            ->with(['unit', 'chargeType', 'billedToOwner', 'billedToUnitTenant', 'cashbookEntries']);

        if (!empty($data['estate_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('estate_id', $data['estate_id']));
        }

        if (!empty($data['charge_type_id'])) {
            $query->where('charge_type_id', $data['charge_type_id']);
        }

        if (!empty($data['billed_to_type'])) {
            $query->where('billed_to_type', $data['billed_to_type']);
        }

        $invoices = $query->get();

        $owners  = [];
        $tenants = [];

        // Ageing bucket totals
        $summary = [
            'current'          => 0.0,
            '30_days'          => 0.0,
            '60_days'          => 0.0,
            '90_days'          => 0.0,
            '120_plus'         => 0.0,
            'total_outstanding'=> 0.0,
        ];

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->outstanding;

            if ($outstanding <= 0) {
                continue;
            }

            // Determine ageing bucket
            $dueDate = $invoice->due_date instanceof Carbon
                ? $invoice->due_date
                : Carbon::parse($invoice->due_date);

            $daysLate = $today->diffInDays($dueDate, false);
            // diffInDays with false: positive = future (not yet due), negative = past (overdue)

            if ($daysLate >= 0) {
                $bucket = 'current';
            } elseif ($daysLate >= -30) {
                $bucket = '30_days';
            } elseif ($daysLate >= -60) {
                $bucket = '60_days';
            } elseif ($daysLate >= -90) {
                $bucket = '90_days';
            } else {
                $bucket = '120_plus';
            }

            $summary[$bucket]          += $outstanding;
            $summary['total_outstanding'] += $outstanding;

            // Build the row entry
            $row = [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'unit_number'    => $invoice->unit?->unit_number,
                'charge_type'    => $invoice->chargeType?->name,
                'billing_period' => $invoice->billing_period?->format('Y-m'),
                'due_date'       => $invoice->due_date?->format('Y-m-d'),
                'outstanding'    => $outstanding,
                'current'        => $bucket === 'current' ? $outstanding : 0,
                '30_days'        => $bucket === '30_days' ? $outstanding : 0,
                '60_days'        => $bucket === '60_days' ? $outstanding : 0,
                '90_days'        => $bucket === '90_days' ? $outstanding : 0,
                '120_plus'       => $bucket === '120_plus' ? $outstanding : 0,
            ];

            $billedToType = $invoice->billed_to_type instanceof BilledToType
                ? $invoice->billed_to_type->value
                : (string) $invoice->billed_to_type;

            if ($billedToType === BilledToType::OWNER->value) {
                $person = $invoice->billedToOwner;
                $row['person_id']   = $person?->id;
                $row['person_name'] = $person?->full_name;
                $row['person_email']= $person?->email;
                $owners[]           = $row;
            } else {
                $person = $invoice->billedToUnitTenant;
                $row['person_id']   = $person?->id;
                $row['person_name'] = $person?->full_name;
                $row['person_email']= $person?->email;
                $tenants[]          = $row;
            }
        }

        // Sort each list by total outstanding descending
        usort($owners, fn($a, $b) => $b['outstanding'] <=> $a['outstanding']);
        usort($tenants, fn($a, $b) => $b['outstanding'] <=> $a['outstanding']);

        return [
            'owners'  => $owners,
            'tenants' => $tenants,
            'summary' => [
                'current'          => round($summary['current'], 2),
                '30_days'          => round($summary['30_days'], 2),
                '60_days'          => round($summary['60_days'], 2),
                '90_days'          => round($summary['90_days'], 2),
                '120_plus'         => round($summary['120_plus'], 2),
                'total_outstanding'=> round($summary['total_outstanding'], 2),
            ],
        ];
    }

    /**
     * Export the age analysis report as CSV, Excel, or PDF.
     *
     * Extra parameters in $data:
     *   _format  — 'csv' | 'xlsx' | 'pdf'  (required)
     *
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAgeAnalysis(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $result = $this->getAgeAnalysis($data);

        $headings = ['Role', 'Name', 'Unit', 'Charge Type', 'Current', '30 Days', '60 Days', '90 Days', '120+ Days', 'Total Outstanding'];

        $rows = [];

        foreach ($result['owners'] as $row) {
            $rows[] = [
                'Owner',
                $row['person_name'] ?? '—',
                $row['unit_number'] ?? '—',
                $row['charge_type'] ?? '—',
                number_format((float) ($row['current'] ?? 0), 2),
                number_format((float) ($row['30_days'] ?? 0), 2),
                number_format((float) ($row['60_days'] ?? 0), 2),
                number_format((float) ($row['90_days'] ?? 0), 2),
                number_format((float) ($row['120_plus'] ?? 0), 2),
                number_format((float) ($row['outstanding'] ?? 0), 2),
            ];
        }

        foreach ($result['tenants'] as $row) {
            $rows[] = [
                'Tenant',
                $row['person_name'] ?? '—',
                $row['unit_number'] ?? '—',
                $row['charge_type'] ?? '—',
                number_format((float) ($row['current'] ?? 0), 2),
                number_format((float) ($row['30_days'] ?? 0), 2),
                number_format((float) ($row['60_days'] ?? 0), 2),
                number_format((float) ($row['90_days'] ?? 0), 2),
                number_format((float) ($row['120_plus'] ?? 0), 2),
                number_format((float) ($row['outstanding'] ?? 0), 2),
            ];
        }

        $format = $data['_format'] ?? 'csv';

        return $this->buildFileResponse(
            $rows,
            $headings,
            'age-analysis-' . now()->format('Y-m-d'),
            $format,
            'Age Analysis Export',
            [
                'Generated'         => now()->format('d M Y'),
                'Total Outstanding' => number_format((float) ($result['summary']['total_outstanding'] ?? 0), 2),
                'Records'           => count($rows),
            ]
        );
    }
}
