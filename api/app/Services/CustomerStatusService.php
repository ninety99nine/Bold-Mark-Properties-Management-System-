<?php

namespace App\Services;

use Exception;
use App\Models\Community;
use App\Models\Unit;
use App\Models\UnitCollectionNote;
use App\Models\UnitStatusHistory;
use App\Enums\CollectionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerStatusService extends BaseService
{
    /**
     * Ageing buckets, oldest → newest.
     */
    private const BUCKETS = ['120_plus', '90_days', '60_days', '30_days', 'current'];

    /**
     * Collection statuses offered on the "Apply Statuses" + filter dropdowns (WeConnectU set).
     */
    private const STATUS_OPTIONS = [
        ['value' => 'none',                'label' => 'No Status'],
        ['value' => 'handed_over',         'label' => 'Handed Over'],
        ['value' => 'payment_arrangement', 'label' => 'Payment Arrangement'],
        ['value' => 'letter_of_demand',    'label' => 'Letter of Demand'],
    ];

    /**
     * Return the customer-status grid for a community.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function showCustomerStatuses(Community $community, array $data): array
    {
        $rows = $this->computeRows($community, $data);

        return [
            'data'         => $rows,
            'report_date'  => $data['status_date'] ?? Carbon::today()->toDateString(),
            'status_options'   => self::STATUS_OPTIONS,
            'interest_options' => [
                ['value' => 'none',   'label' => 'No Status'],
                ['value' => 'exempt', 'label' => 'Exempt from Interest'],
            ],
            // Grouped "Select Status" apply dropdown (WeConnectU: Collection /
            // Interest / Debt sections). Values are namespaced by domain.
            'apply_status_options' => [
                ['group' => 'Collection Status', 'options' => [
                    ['value' => 'collection:none',                'label' => 'No Status'],
                    ['value' => 'collection:letter_of_demand',    'label' => 'Letter of Demand'],
                    ['value' => 'collection:handed_over',         'label' => 'Handed Over'],
                    ['value' => 'collection:payment_arrangement', 'label' => 'Payment Arrangement'],
                ]],
                ['group' => 'Interest Status', 'options' => [
                    ['value' => 'interest:none',   'label' => 'No Status'],
                    ['value' => 'interest:exempt', 'label' => 'Exempt from Interest'],
                ]],
                ['group' => 'Debt Statuses', 'options' => [
                    ['value' => 'debt:none',          'label' => 'No Status'],
                    ['value' => 'debt:first_notice',  'label' => '1st Notice'],
                    ['value' => 'debt:second_notice', 'label' => '2nd Notice'],
                    ['value' => 'debt:final_notice',  'label' => 'Final Notice'],
                ]],
            ],
            'customer_groups'  => $community->customerGroups()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])
                ->all(),
            // Attorneys for the Handed Over "Select Attorney" dropdown — the
            // distinct attorney emails already captured on the community's units.
            'attorney_options' => Unit::where('community_id', $community->id)
                ->whereNotNull('attorney_email')
                ->where('attorney_email', '!=', '')
                ->distinct()
                ->orderBy('attorney_email')
                ->pluck('attorney_email')
                ->map(fn ($e) => ['value' => $e, 'label' => $e])
                ->all(),
        ];
    }

    /**
     * Apply a collection status (+ optional note) to the selected customers.
     *
     * @param Community $community
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function applyStatuses(Community $community, array $data): array
    {
        $user      = Auth::user();
        $unitIds   = $data['unit_ids'] ?? [];
        $note      = trim((string) ($data['note'] ?? '')) ?: null;
        $date      = $data['status_date'] ?? Carbon::today()->toDateString();
        $changedBy = $user?->name ?? $user?->full_name ?? 'System';

        // The apply dropdown sends a domain-namespaced value, e.g.
        // "collection:letter_of_demand" or "interest:exempt". A bare value
        // (legacy) is treated as a collection status.
        $raw    = $data['status'] ?? null;
        $domain = null;
        $value  = null;
        if ($raw !== null && $raw !== '') {
            [$domain, $value] = str_contains($raw, ':') ? explode(':', $raw, 2) : ['collection', $raw];
        }

        // Handed Over "Select Attorney" — stored on the unit when supplied.
        $attorney = trim((string) ($data['attorney'] ?? '')) ?: null;

        $units = Unit::whereIn('id', $unitIds)
            ->where('community_id', $community->id)
            ->where('organization_id', $user->organization_id)
            ->get();

        if ($units->isEmpty()) {
            throw new Exception('No customers selected');
        }

        DB::transaction(function () use ($units, $domain, $value, $attorney, $note, $date, $changedBy, $user, $community) {
            foreach ($units as $unit) {
                $historyStatus = $unit->collection_status?->value ?? 'none';

                if ($domain === 'collection' || $domain === 'debt') {
                    $changes = ['collection_status' => $value];
                    // Capture the selected attorney on a hand-over.
                    if ($value === 'handed_over' && $attorney) {
                        $changes['attorney_email'] = $attorney;
                    }
                    $unit->update($changes);
                    $historyStatus = $value;
                } elseif ($domain === 'interest') {
                    $unit->update(['interest_exempt' => $value === 'exempt']);
                    $historyStatus = $value === 'exempt' ? 'exempt_from_interest' : 'interest_no_status';
                }

                UnitStatusHistory::create([
                    'status'          => $historyStatus,
                    'note'            => $note,
                    'status_date'     => $date,
                    'is_automatic'    => false,
                    'changed_by_name' => $changedBy,
                    'unit_id'         => $unit->id,
                    'community_id'    => $community->id,
                    'organization_id' => $unit->organization_id,
                    'user_id'         => $user?->id,
                ]);

                if ($note) {
                    UnitCollectionNote::create([
                        'unit_id'         => $unit->id,
                        'organization_id' => $unit->organization_id,
                        // Status-change notes are system entries (read-only in the
                        // notes modal), prefixed with the applied status label.
                        'note'            => trim($this->batchStatusLabel($historyStatus) . ': ' . $note),
                        'is_system'       => true,
                        'created_by_name' => $changedBy,
                        'user_id'         => $user?->id,
                    ]);
                }
            }
        });

        $count = $units->count();

        return ['message' => "{$count} customer" . ($count === 1 ? '' : 's') . ' updated'];
    }

    /**
     * Stream the customer-statuses report as an xlsx (WeConnectU layout).
     *
     * @param Community $community
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadStatuses(Community $community, array $data): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rows       = $this->computeRows($community, $data);
        $reportDate = $data['status_date'] ?? Carbon::today()->toDateString();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Worksheet');

        foreach (range('A', 'J') as $i => $col) {
            $sheet->getColumnDimension($col)->setWidth([20, 50, 20, 20, 20, 20, 20, 20, 50, 120][$i]);
        }

        $sheet->setCellValue('A1', $community->name . ' ' . $this->entityLabel($community));
        $sheet->setCellValue('A2', 'Customer Statuses');
        $sheet->setCellValue('A3', 'Report Date: ' . $reportDate);
        $sheet->setCellValue('A4', 'Generated: ' . Carbon::today()->toDateString());
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

        $headers = ['Unit No', 'Customer', '120+ Days', '90+ Days', '60+ Days', '30+ Days', 'Current', 'Balance', 'Status', 'Note'];
        $col = 'A';
        foreach ($headers as $h) {
            $cell = $col . '6';
            $sheet->setCellValue($cell, $h);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3A5C');
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        $r = 7;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $r, $row['unit_number']);
            $sheet->setCellValue('B' . $r, trim($row['customer_code'] . ' ' . $row['customer_name']));
            $sheet->setCellValue('C' . $r, $row['120_plus']);
            $sheet->setCellValue('D' . $r, $row['90_days']);
            $sheet->setCellValue('E' . $r, $row['60_days']);
            $sheet->setCellValue('F' . $r, $row['30_days']);
            $sheet->setCellValue('G' . $r, $row['current']);
            $sheet->setCellValue('H' . $r, $row['balance']);
            $sheet->setCellValue('I' . $r, $row['collection_status'] === 'none' ? ' ' : $row['collection_status_label']);
            $sheet->setCellValue('J' . $r, $row['notes_text']);
            $r++;
        }

        $writer = new XlsxWriter($spreadsheet);
        $filename = 'customer statuses-' . strtolower($community->name . ' ' . $this->entityLabel($community)) . '-' . $reportDate . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Return the collection-status history for a community (manual changes).
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function showStatusHistory(Community $community, array $data): array
    {
        $user = Auth::user();

        $rows = UnitStatusHistory::where('community_id', $community->id)
            ->where('organization_id', $user->organization_id)
            ->where('is_automatic', false)
            ->orderByDesc('status_date')
            ->orderByDesc('created_at')
            ->limit(3000)
            ->get();

        // Group per apply-action (a "batch"): same date + status + note.
        $batches = $rows
            ->groupBy(fn (UnitStatusHistory $h) => implode('|', [
                $h->status_date?->toDateString(),
                $h->status,
                (string) $h->note,
            ]))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'status_date'  => $first->status_date?->toDateString(),
                    'status'       => $first->status,
                    'status_label' => $this->batchStatusLabel($first->status),
                    'customers'    => $group->pluck('unit_id')->unique()->count(),
                    'note'         => $first->note,
                    'email'        => null,
                    'sms'          => null,
                ];
            })
            ->values()
            ->sortByDesc('status_date')
            ->values()
            ->all();

        return ['batches' => $batches];
    }

    /**
     * "CODE: Name" customer label for a history row (WeConnectU format).
     *
     * @param UnitStatusHistory $h
     * @return string
     */
    private function customerLabel(UnitStatusHistory $h): string
    {
        $code = $h->unit?->customer_code ?: $h->unit?->owner?->customer_code;
        $name = $h->unit?->owner?->full_name;

        if ($code && $name) {
            return "{$code}: {$name}";
        }

        return $code ?: ($name ?: '—');
    }

    /**
     * The WeConnectU "Status Batches" label for a stored history status value.
     *
     * @param string|null $status
     * @return string
     */
    private function batchStatusLabel(?string $status): string
    {
        return match ($status) {
            'none', null          => 'Removed Collection Type',
            'exempt_from_interest' => 'Exempt from Interest',
            'interest_no_status'   => '',
            default                => CollectionStatus::tryFrom($status)?->label() ?: $status,
        };
    }

    /**
     * Return automatic collection-status changes for a community.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function showAutomaticStatusChanges(Community $community, array $data): array
    {
        return $this->historyRows($community, true);
    }

    /**
     * Assemble history rows (manual or automatic).
     *
     * @param Community $community
     * @param bool $automatic
     * @return array
     */
    private function historyRows(Community $community, bool $automatic): array
    {
        $user = Auth::user();

        return UnitStatusHistory::where('community_id', $community->id)
            ->where('organization_id', $user->organization_id)
            ->where('is_automatic', $automatic)
            ->with('unit.owner')
            ->orderByDesc('status_date')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (UnitStatusHistory $h) => [
                'id'            => $h->id,
                'status_date'   => $h->status_date?->toDateString(),
                'unit_number'   => $h->unit?->unit_number,
                'customer'      => $this->customerLabel($h),
                'status'        => $h->status,
                'status_label'  => CollectionStatus::tryFrom($h->status)?->label() ?: $h->status,
                'note'          => $h->note,
                'changed_by'    => $h->changed_by_name,
                'created_at'    => $h->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Compute the per-customer ageing rows for a community (all units, not just arrears).
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    private function computeRows(Community $community, array $data): array
    {
        $user = Auth::user();
        $asAt = Carbon::parse($data['status_date'] ?? Carbon::today()->toDateString());

        $units = Unit::where('community_id', $community->id)
            ->where('organization_id', $user->organization_id)
            ->with(['owner.customerGroups'])
            ->withCount('collectionNotes')
            ->get();

        $unitIds = $units->pluck('id')->all();

        // Who applied each customer's current collection status (acting user).
        $statusActors = UnitStatusHistory::latestActorsByUnit($unitIds);

        // ── Customer subledger (GL) aged as at the report date ──
        // Every customer transaction is a CUSTOMER journal line. Debits age by
        // their due_date into the buckets; credits net oldest-bucket-first —
        // keeping Status Management in lock-step with Age Analysis, the customer
        // statement and UnitBalanceService.
        $bucketsByUnit = [];
        $creditsByUnit = [];

        $journalByUnit = (new JournalPostingService())->agedCustomerByUnit($unitIds, $asAt->toDateString());
        foreach ($journalByUnit as $uid => $effect) {
            if ($effect['credit'] > 0) {
                $creditsByUnit[$uid] = ($creditsByUnit[$uid] ?? 0) + $effect['credit'];
            }
            foreach ($effect['debits'] as $debit) {
                $bucket                       = $this->bucketFor($debit['date'], $asAt);
                $bucketsByUnit[$uid]          ??= array_fill_keys(self::BUCKETS, 0.0);
                $bucketsByUnit[$uid][$bucket] += $debit['amount'];
            }
        }

        // ── Filters ──
        $statusFilter   = $data['status'] ?? null;         // collection_status value
        $interestFilter = $data['interest_status'] ?? null; // 'none' | 'exempt'
        $groupFilter    = $data['customer_group'] ?? null;  // customer group id
        $hideZeros      = filter_var($data['hide_zeros'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hideCredits    = filter_var($data['hide_credits'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $search         = mb_strtolower(trim((string) ($data['_search'] ?? '')));

        $rows = [];
        foreach ($units as $unit) {
            $buckets = $bucketsByUnit[$unit->id] ?? array_fill_keys(self::BUCKETS, 0.0);
            $credit  = (float) ($creditsByUnit[$unit->id] ?? 0);

            foreach (self::BUCKETS as $b) {
                if ($credit <= 0) break;
                $reduce = min($credit, $buckets[$b]);
                $buckets[$b] -= $reduce;
                $credit      -= $reduce;
            }

            $arrears = array_sum($buckets);
            $balance = round($arrears - $credit, 2);

            $owner  = $unit->owner;
            $status = $unit->collection_status instanceof CollectionStatus
                ? $unit->collection_status
                : CollectionStatus::tryFrom((string) $unit->collection_status) ?? CollectionStatus::NONE;

            $groupIds = $owner?->customerGroups->pluck('id')->all() ?? [];

            // Apply filters
            if ($statusFilter && $status->value !== $statusFilter) continue;
            if ($interestFilter === 'exempt' && ! $unit->interest_exempt) continue;
            if ($interestFilter === 'none' && $unit->interest_exempt) continue;
            if ($groupFilter && ! in_array($groupFilter, $groupIds, true)) continue;
            if ($hideZeros && abs($balance) < 0.005) continue;
            if ($hideCredits && $balance < 0) continue;
            if ($search !== '') {
                $hay = mb_strtolower(trim(($unit->customer_code ?? '') . ' ' . ($owner?->full_name ?? '') . ' ' . ($unit->unit_number ?? '') . ' ' . ($owner?->email ?? '')));
                if (! str_contains($hay, $search)) continue;
            }

            $notesText = $unit->collectionNotes
                ->map(fn ($n) => $n->created_at?->toDateString() . ': ' . $n->note)
                ->implode("\n");

            $rows[] = [
                'unit_id'                 => $unit->id,
                'unit_number'             => $unit->unit_number,
                'customer_code'           => $unit->customer_code ?: ($owner?->customer_code ?? ''),
                'customer_name'           => $owner?->full_name ?? '—',
                'customer_email'          => $owner?->email,
                'customer_phone'          => $owner?->phone,
                'collection_status'       => $status->value,
                'collection_status_label' => $status->label(),
                'status_changed_by'       => $statusActors[$unit->id][$status->value] ?? null,
                'debit_order'             => (bool) $unit->debit_order,
                'transfer_active'         => (bool) $unit->transfer_active,
                'interest_exempt'         => (bool) $unit->interest_exempt,
                'notes_count'             => (int) ($unit->collection_notes_count ?? 0),
                'notes_text'              => $notesText,
                '120_plus'                => round($buckets['120_plus'], 2),
                '90_days'                 => round($buckets['90_days'], 2),
                '60_days'                 => round($buckets['60_days'], 2),
                '30_days'                 => round($buckets['30_days'], 2),
                'current'                 => round($buckets['current'], 2),
                'balance'                 => $balance,
            ];
        }

        usort($rows, fn ($a, $b) => $this->unitSortKey($a['unit_number']) <=> $this->unitSortKey($b['unit_number'])
            ?: strcmp((string) $a['unit_number'], (string) $b['unit_number']));

        return $rows;
    }

    /**
     * Resolve the ageing bucket for an invoice due date relative to a report date.
     *
     * @param mixed $dueDate
     * @param Carbon $asAt
     * @return string
     */
    private function bucketFor($dueDate, Carbon $asAt): string
    {
        $due      = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);
        $daysLate = $asAt->diffInDays($due, false);

        return match (true) {
            $daysLate >= 0   => 'current',
            $daysLate >= -30 => '30_days',
            $daysLate >= -60 => '60_days',
            $daysLate >= -90 => '90_days',
            default          => '120_plus',
        };
    }

    /**
     * Numeric sort key from a unit number (e.g. "U12" → 12).
     *
     * @param string|null $unitNumber
     * @return int
     */
    private function unitSortKey(?string $unitNumber): int
    {
        if (! $unitNumber) {
            return PHP_INT_MAX;
        }
        preg_match('/(\d+)(?!.*\d)/', $unitNumber, $m);
        return isset($m[1]) ? (int) $m[1] : PHP_INT_MAX;
    }

    /**
     * Human-readable entity label for the community (e.g. "Body Corporate").
     *
     * @param Community $community
     * @return string
     */
    private function entityLabel(Community $community): string
    {
        $et = $community->entity_type instanceof \BackedEnum ? $community->entity_type->value : $community->entity_type;

        return ($community->suppress_entity_type || ! $et) ? '' : ucwords(str_replace('_', ' ', (string) $et));
    }
}
