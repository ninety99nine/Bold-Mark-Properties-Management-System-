<?php

namespace App\Services;

use App\Exports\DetailedLedgerExport;
use App\Models\Community;
use App\Models\LedgerReportBatch;
use App\Models\Unit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class DetailedLedgerService extends BaseService
{
    /**
     * Build the WeConnectU Detailed Customer Ledger — one transaction ledger per
     * selected customer (Date · Source · Description · Remarks · Debit · Credit ·
     * Balance) with an opening "Balance b/f", optional invoice line items, and a
     * per-customer totals row.
     *
     * @param Community $community
     * @param array $data
     * @return array{ledgers: array}
     */
    public function run(Community $community, array $data): array
    {
        $from          = $data['date_from'] ?? null;
        $to            = $data['date_to'] ?? null;
        $showLineItems = filter_var($data['show_line_items'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hideZero      = filter_var($data['hide_zero'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $units = $this->resolveUnits($community, $data);

        $ledgers = [];
        foreach ($units as $unit) {
            $ledger = $this->buildLedger($unit, $from, $to, $showLineItems);

            if ($hideZero && abs($ledger['totals']['balance']) < 0.005 && count($ledger['rows']) <= 2) {
                continue;
            }

            $ledgers[] = $ledger;
        }

        // WeConnectU orders the sections by unit number.
        usort($ledgers, fn ($a, $b) => $this->unitSortKey($a['unit_number']) <=> $this->unitSortKey($b['unit_number']));

        return ['ledgers' => $ledgers];
    }

    /**
     * Download the ledger as a WeConnectU-faithful Excel workbook.
     *
     * @param Community $community
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export(Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $ledgers = $this->run($community, $data)['ledgers'];
        $from    = $data['date_from'] ?? '';
        $to      = $data['date_to'] ?? '';

        $filename = trim('detailed customer ledger-' . strtolower((string) ($community->name ?? 'community')) . '-' . $from . ' to ' . $to);

        return Excel::download(
            new DetailedLedgerExport((string) $community->name, $from, $to, $ledgers),
            $filename . '.xlsx'
        );
    }

    /**
     * Record an "Email Report" request (WeConnectU Recent Email Reports). The
     * report is generated to count the covered accounts and logged; the actual
     * outbound email is deferred (Twilio/Resend queue). The row can be
     * re-downloaded later from the Recent Email Reports panel.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function requestEmailReport(Community $community, array $data): array
    {
        $result = $this->run($community, $data);
        $user   = Auth::user();

        $batch = LedgerReportBatch::create([
            'community_id'         => $community->id,
            'organization_id'      => $community->organization_id,
            'requested_by_user_id' => $user?->id,
            'date_from'            => $data['date_from'] ?? null,
            'date_to'              => $data['date_to'] ?? null,
            'account_count'        => count($result['ledgers']),
            'status'               => 'completed',
            'generated_at'         => now(),
            'filters'              => Arr::only($data, ['all_customers', 'customer_ids', 'group_ids', 'statuses', 'hide_zero', 'show_line_items']),
        ]);

        return [
            'batch'   => $this->batchArray($batch),
            'message' => 'The report has been generated and e-mailed to you.',
        ];
    }

    /**
     * List the community's recent ledger report requests, newest first.
     *
     * @param Community $community
     * @return array
     */
    public function listReports(Community $community): array
    {
        return LedgerReportBatch::where('community_id', $community->id)
            ->orderByDesc('generated_at')
            ->limit(15)
            ->get()
            ->map(fn (LedgerReportBatch $b) => $this->batchArray($b))
            ->all();
    }

    /**
     * Re-download a stored report's Excel workbook (regenerated from its saved
     * date range + filters).
     *
     * @param Community $community
     * @param LedgerReportBatch $batch
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadReport(Community $community, LedgerReportBatch $batch): \Symfony\Component\HttpFoundation\Response
    {
        $from = optional($batch->date_from)->toDateString();
        $to   = optional($batch->date_to)->toDateString();

        $data    = array_merge($batch->filters ?? [], ['date_from' => $from, 'date_to' => $to]);
        $ledgers = $this->run($community, $data)['ledgers'];

        $filename = 'cl-' . Str::slug((string) $community->name, '_') . '-' . optional($batch->generated_at)->format('Y-m-d-His');

        return Excel::download(
            new DetailedLedgerExport((string) $community->name, (string) $from, (string) $to, $ledgers),
            $filename . '.xlsx'
        );
    }

    /**
     * Shape a report batch for the frontend (WeConnectU columns).
     *
     * @param LedgerReportBatch $batch
     * @return array
     */
    private function batchArray(LedgerReportBatch $batch): array
    {
        return [
            'id'          => $batch->id,
            'date_range'  => optional($batch->date_from)->toDateString() . ' - ' . optional($batch->date_to)->toDateString(),
            'status'      => $batch->account_count . ' Account(s)',
            'report_date' => optional($batch->generated_at)->format('Y-m-d H:i'),
        ];
    }

    /**
     * Resolve the units (customers) to include based on the filters.
     *
     * @param Community $community
     * @param array $data
     * @return \Illuminate\Support\Collection<int, Unit>
     */
    private function resolveUnits(Community $community, array $data): \Illuminate\Support\Collection
    {
        $query = Unit::where('community_id', $community->id)->with('owner');

        $unitIds  = array_filter((array) ($data['customer_ids'] ?? []));
        $groupIds = array_filter((array) ($data['group_ids'] ?? []));
        $statuses = array_filter((array) ($data['statuses'] ?? []));
        $all      = filter_var($data['all_customers'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $all && (! empty($unitIds) || ! empty($groupIds))) {
            $query->where(function ($q) use ($unitIds, $groupIds) {
                if (! empty($unitIds)) {
                    $q->orWhereIn('id', $unitIds);
                }
                if (! empty($groupIds)) {
                    $q->orWhereHas('owner.customerGroups', fn ($g) => $g->whereIn('customer_groups.id', $groupIds));
                }
            });
        }

        if (! empty($statuses)) {
            $query->whereIn('collection_status', $statuses);
        }

        return $query->get();
    }

    /**
     * Build a single customer's ledger from the general ledger (customer
     * subledger) alone. Every row is a CUSTOMER journal line: an invoice posts
     * one debit per item (carrying the item's description), a credit note / an
     * allocated receipt posts a credit. When line items are hidden, consecutive
     * lines sharing the same source batch are collapsed into a single row per
     * source document.
     *
     * @param Unit $unit
     * @param string|null $from
     * @param string|null $to
     * @param bool $showLineItems
     * @return array
     */
    private function buildLedger(Unit $unit, ?string $from, ?string $to, bool $showLineItems): array
    {
        $posting = new JournalPostingService();

        // Opening "Balance b/f" = debits − credits strictly before the "from" date.
        $opening = $posting->openingBefore($unit->id, $from);

        $lines = $posting->eventsForUnit($unit->id, $from, $to);

        $events = [];
        if ($showLineItems) {
            // WeConnectU expands an invoice into one row per line item: the Source
            // reads "Invoice INV05281 (Line 2)" and the Description carries the item
            // (e.g. "Levies"). Number the lines 1-based within each source document.
            $lineNoByBatch = [];
            foreach ($lines as $j) {
                $isInvoice = ! empty($j['invoice_number']);
                $source    = $j['source'];

                if ($isInvoice) {
                    $key                 = $j['batch_id'] ?? $j['invoice_id'];
                    $lineNoByBatch[$key] = ($lineNoByBatch[$key] ?? 0) + 1;
                    $source              = 'Invoice ' . $j['invoice_number'] . ' (Line ' . $lineNoByBatch[$key] . ')';
                }

                $events[] = [
                    'date'           => $j['date'],
                    'source'         => $source,
                    'description'    => $j['description'],
                    'remarks'        => '',
                    'debit'          => $j['debit'],
                    'credit'         => $j['credit'],
                    'invoice_id'     => $j['invoice_id'] ?? null,
                    'invoice_number' => $j['invoice_number'] ?? null,
                    'allocated_by'   => $j['allocated_by'] ?? null,
                    'allocated_at'   => $j['allocated_at'] ?? null,
                    'allocated_on'   => $j['allocated_on'] ?? null,
                ];
            }
        } else {
            // Collapse each source document (a batch) into one row per source. An
            // invoice shows its number (INV05281) in the Description — a link to the
            // invoice PDF, exactly like WeConnectU.
            $grouped = [];
            foreach ($lines as $j) {
                $key = $j['batch_id'] ?? uniqid('b', true);
                if (! isset($grouped[$key])) {
                    $isInvoice = ! empty($j['invoice_number']);
                    $grouped[$key] = [
                        'date'           => $j['date'],
                        'source'         => $j['source'],
                        'description'    => $isInvoice ? $j['invoice_number'] : $j['description'],
                        'remarks'        => '',
                        'debit'          => 0.0,
                        'credit'         => 0.0,
                        'invoice_id'     => $j['invoice_id'] ?? null,
                        'invoice_number' => $j['invoice_number'] ?? null,
                        'allocated_by'   => $j['allocated_by'] ?? null,
                        'allocated_at'   => $j['allocated_at'] ?? null,
                        'allocated_on'   => $j['allocated_on'] ?? null,
                    ];
                }
                $grouped[$key]['debit']  += $j['debit'];
                $grouped[$key]['credit'] += $j['credit'];
            }
            $events = array_values($grouped);
        }

        usort($events, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        $balance     = $opening;
        $debitTotal  = 0.0;
        $creditTotal = 0.0;
        $rows        = [[
            'date'           => $from ?: ($events[0]['date'] ?? $to),
            'source'         => '',
            'description'    => 'Balance b/f',
            'remarks'        => '',
            'debit'          => round($opening, 2),
            'credit'         => 0.0,
            'balance'        => round($opening, 2),
            'invoice_id'     => null,
            'invoice_number' => null,
            'allocated_by'   => null,
            'allocated_at'   => null,
            'allocated_on'   => null,
        ]];

        foreach ($events as $e) {
            $balance     += $e['debit'] - $e['credit'];
            $debitTotal  += $e['debit'];
            $creditTotal += $e['credit'];
            $rows[]       = [
                'date'           => $e['date'],
                'source'         => $e['source'],
                'description'    => $e['description'],
                'remarks'        => $e['remarks'],
                'debit'          => round($e['debit'], 2),
                'credit'         => round($e['credit'], 2),
                'balance'        => round($balance, 2),
                'invoice_id'     => $e['invoice_id'] ?? null,
                'invoice_number' => $e['invoice_number'] ?? null,
                'allocated_by'   => $e['allocated_by'] ?? null,
                'allocated_at'   => $e['allocated_at'] ?? null,
                'allocated_on'   => $e['allocated_on'] ?? null,
            ];
        }

        $rows = $this->insertBalancePaidMarkers($rows);

        $code = $unit->owner?->customer_code ?: $unit->customer_code;

        return [
            'unit_id'       => $unit->id,
            'unit_number'   => $unit->unit_number,
            'customer_code' => $code,
            'customer_name' => $unit->owner?->full_name ?? '—',
            'heading'       => trim(($code ? $code . ' - ' : '') . ($unit->owner?->full_name ?? '—')),
            'rows'          => $rows,
            'totals'        => [
                'debit'   => round($debitTotal, 2),
                'credit'  => round($creditTotal, 2),
                'balance' => round($balance, 2),
            ],
        ];
    }

    /**
     * Insert WeConnectU "Balance-paid / Balance Paid" marker rows. When a receipt
     * (credit) settles the account — the running balance crosses from owing (> 0)
     * to paid-up / in-credit (≤ 0) — WeConnectU drops a zero-value marker row
     * dated on the ALLOCATION date (when the receipt was matched to the account),
     * which is typically a day or two after the receipt's own transaction date.
     * We take that date from the settling receipt's `allocated_on`, falling back
     * to the receipt date when the entry has no recorded allocation timestamp.
     *
     * @param array $rows
     * @return array
     */
    private function insertBalancePaidMarkers(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $out[] = $row;

            if ($i === 0) {
                continue; // never after the opening "Balance b/f"
            }

            $prevBalance = $rows[$i - 1]['balance'];
            $settledNow  = $row['credit'] > 0.005 && $prevBalance > 0.005 && $row['balance'] <= 0.005;

            if ($settledNow) {
                $out[] = [
                    'date'           => $row['allocated_on'] ?: $row['date'],
                    'source'         => 'Balance-paid',
                    'description'    => 'Balance Paid',
                    'remarks'        => '',
                    'debit'          => 0.0,
                    'credit'         => 0.0,
                    'balance'        => $row['balance'],
                    'invoice_id'     => null,
                    'invoice_number' => null,
                    'allocated_by'   => null,
                    'allocated_at'   => null,
                    'allocated_on'   => null,
                ];
            }
        }

        return $out;
    }

    /**
     * Natural sort key for a unit number (numeric part first).
     *
     * @param string|null $unitNumber
     * @return int
     */
    private function unitSortKey(?string $unitNumber): int
    {
        preg_match('/\d+/', (string) $unitNumber, $m);

        return isset($m[0]) ? (int) $m[0] : PHP_INT_MAX;
    }
}
