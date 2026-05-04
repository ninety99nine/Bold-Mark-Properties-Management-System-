<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\CashbookEntry;
use App\Enums\BilledToType;
use App\Enums\CashbookEntryType;
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
        $tenantId = $user->organization_id;
        $today    = Carbon::today();

        // Bucket order oldest→newest (credits are applied oldest-first)
        $bucketOrder = ['120_plus', '90_days', '60_days', '30_days', 'current'];

        $query = Invoice::where('organization_id', $tenantId)
            ->whereIn('status', [
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PARTIALLY_PAID->value,
            ])
            ->with(['unit', 'chargeType', 'billedToOwner', 'billedToUnitTenant', 'cashbookEntries']);

        if (!empty($data['country'])) {
            $query->whereHas('unit.estate', fn($q) => $q->where('country', $data['country']));
        }

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

        // ── Step 1: Build raw rows grouped by unit_id ─────────────────────
        $entriesByUnit = []; // unit_id => [ ['row' => [...], 'bucket' => '...', 'billed_to_type' => '...'], ... ]

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->outstanding;
            if ($outstanding <= 0) continue;

            $dueDate  = $invoice->due_date instanceof Carbon
                ? $invoice->due_date
                : Carbon::parse($invoice->due_date);
            $daysLate = $today->diffInDays($dueDate, false);

            if ($daysLate >= 0)       $bucket = 'current';
            elseif ($daysLate >= -30) $bucket = '30_days';
            elseif ($daysLate >= -60) $bucket = '60_days';
            elseif ($daysLate >= -90) $bucket = '90_days';
            else                      $bucket = '120_plus';

            $billedToType = $invoice->billed_to_type instanceof BilledToType
                ? $invoice->billed_to_type->value
                : (string) $invoice->billed_to_type;

            $person = $billedToType === BilledToType::OWNER->value
                ? $invoice->billedToOwner
                : $invoice->billedToUnitTenant;

            $unitId = $invoice->unit?->id ?? 'unknown';

            $entriesByUnit[$unitId][] = [
                'billed_to_type' => $billedToType,
                'bucket'         => $bucket,
                'row'            => [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'unit_id'        => $invoice->unit?->id,
                    'estate_id'      => $invoice->unit?->estate_id,
                    'unit_number'    => $invoice->unit?->unit_number,
                    'charge_type'    => $invoice->chargeType?->name,
                    'billing_period' => $invoice->billing_period?->format('Y-m'),
                    'due_date'       => $invoice->due_date?->format('Y-m-d'),
                    'person_id'      => $person?->id,
                    'person_name'    => $person?->full_name,
                    'person_email'   => $person?->email,
                    'outstanding'    => $outstanding,
                    'current'        => $bucket === 'current'  ? $outstanding : 0,
                    '30_days'        => $bucket === '30_days'  ? $outstanding : 0,
                    '60_days'        => $bucket === '60_days'  ? $outstanding : 0,
                    '90_days'        => $bucket === '90_days'  ? $outstanding : 0,
                    '120_plus'       => $bucket === '120_plus' ? $outstanding : 0,
                ],
            ];
        }

        // ── Step 2: Load unallocated credits per unit ─────────────────────
        // Unallocated credits = cashbook entries (credit type, no invoice_id)
        // These represent advance payments / overpayments that have not yet
        // been matched to an invoice. Per spec they must net against arrears.
        $unitIds       = array_keys($entriesByUnit);
        $creditsByUnit = [];

        if (!empty($unitIds)) {
            $creditsQuery = CashbookEntry::where('organization_id', $tenantId)
                ->whereIn('unit_id', $unitIds)
                ->whereNull('invoice_id')
                ->where('type', CashbookEntryType::CREDIT->value);

            if (!empty($data['country'])) {
                $creditsQuery->whereHas('estate', fn($q) => $q->where('country', $data['country']));
            }

            $creditsByUnit = $creditsQuery
                ->selectRaw('unit_id, SUM(amount) as total_credits')
                ->groupBy('unit_id')
                ->pluck('total_credits', 'unit_id')
                ->map(fn($v) => (float) $v)
                ->toArray();
        }

        // ── Step 3: Apply credits oldest-first to reduce each unit's rows ─
        foreach ($entriesByUnit as $unitId => &$entries) {
            $credit = (float) ($creditsByUnit[$unitId] ?? 0);
            if ($credit <= 0) continue;

            // Sort oldest bucket first so the most overdue amounts are offset first
            usort($entries, fn($a, $b) =>
                array_search($a['bucket'], $bucketOrder) <=>
                array_search($b['bucket'], $bucketOrder)
            );

            foreach ($entries as &$entry) {
                if ($credit <= 0) break;
                $bucket  = $entry['bucket'];
                $reduce  = min($credit, $entry['row']['outstanding']);
                $entry['row']['outstanding'] -= $reduce;
                $entry['row'][$bucket]       -= $reduce;
                $credit                      -= $reduce;
            }
            unset($entry);
        }
        unset($entries);

        // ── Step 4: Collect non-zero rows, build owners/organizations + summary ─
        $owners  = [];
        $organizations = [];
        $summary = [
            'current'           => 0.0,
            '30_days'           => 0.0,
            '60_days'           => 0.0,
            '90_days'           => 0.0,
            '120_plus'          => 0.0,
            'total_outstanding' => 0.0,
            'current_count'     => 0,
            'd30_count'         => 0,
            'd60_count'         => 0,
            'd90_count'         => 0,
            'd120_count'        => 0,
            'total_count'       => 0,
            'people_count'      => 0,
        ];

        // Track distinct people per bucket
        $peopleSets = [
            'current_people'  => [],
            'd30_people'      => [],
            'd60_people'      => [],
            'd90_people'      => [],
            'd120_people'     => [],
        ];

        foreach ($entriesByUnit as $entries) {
            foreach ($entries as $entry) {
                $row = $entry['row'];
                if ($row['outstanding'] <= 0) continue;

                $personKey = ($row['person_name'] ?? '') . '__' . ($row['unit_number'] ?? '');

                foreach (['current', '30_days', '60_days', '90_days', '120_plus'] as $b) {
                    $summary[$b] += $row[$b];
                    if ($row[$b] > 0) {
                        $countKey = match($b) {
                            'current'  => 'current_count',
                            '30_days'  => 'd30_count',
                            '60_days'  => 'd60_count',
                            '90_days'  => 'd90_count',
                            '120_plus' => 'd120_count',
                        };
                        $summary[$countKey]++;

                        $peopleKey = match($b) {
                            'current'  => 'current_people',
                            '30_days'  => 'd30_people',
                            '60_days'  => 'd60_people',
                            '90_days'  => 'd90_people',
                            '120_plus' => 'd120_people',
                        };
                        $peopleSets[$peopleKey][$personKey] = true;
                    }
                }
                $summary['total_outstanding'] += $row['outstanding'];
                $summary['total_count']++;

                if ($entry['billed_to_type'] === BilledToType::OWNER->value) {
                    $owners[] = $row;
                } else {
                    $organizations[] = $row;
                }
            }
        }

        usort($owners,  fn($a, $b) => $b['outstanding'] <=> $a['outstanding']);
        usort($organizations, fn($a, $b) => $b['outstanding'] <=> $a['outstanding']);

        $summary['people_count'] = count($owners) + count($organizations);
        $summary['current_people_count'] = count($peopleSets['current_people']);
        $summary['d30_people_count']     = count($peopleSets['d30_people']);
        $summary['d60_people_count']     = count($peopleSets['d60_people']);
        $summary['d90_people_count']     = count($peopleSets['d90_people']);
        $summary['d120_people_count']    = count($peopleSets['d120_people']);

        return [
            'owners'  => $owners,
            'organizations' => $organizations,
            'summary' => [
                'current'           => round($summary['current'], 2),
                '30_days'           => round($summary['30_days'], 2),
                '60_days'           => round($summary['60_days'], 2),
                '90_days'           => round($summary['90_days'], 2),
                '120_plus'          => round($summary['120_plus'], 2),
                'total_outstanding' => round($summary['total_outstanding'], 2),
                'current_count'     => $summary['current_count'],
                'd30_count'         => $summary['d30_count'],
                'd60_count'         => $summary['d60_count'],
                'd90_count'         => $summary['d90_count'],
                'd120_count'        => $summary['d120_count'],
                'total_count'           => $summary['total_count'],
                'people_count'          => $summary['people_count'],
                'current_people_count'  => $summary['current_people_count'],
                'd30_people_count'      => $summary['d30_people_count'],
                'd60_people_count'      => $summary['d60_people_count'],
                'd90_people_count'      => $summary['d90_people_count'],
                'd120_people_count'     => $summary['d120_people_count'],
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

        foreach ($result['organizations'] as $row) {
            $rows[] = [
                'Organization',
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
