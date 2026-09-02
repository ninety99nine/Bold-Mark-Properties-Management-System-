<?php

namespace App\Services;

use App\Exports\DetailedSupplierLedgerExport;
use App\Models\Community;
use App\Models\Supplier;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Build the WeConnectU "Detailed Supplier Ledger" — one transaction ledger per
 * selected supplier (Date · Source · Description · Remarks · Debit · Credit ·
 * Balance) with an opening "Balance b/f" and a per-supplier totals row.
 *
 * Every row is read from the General Ledger's Accounts-Payable subledger (the
 * SUPPLIER journal lines) via {@see SupplierLedgerService}, so a supplier invoice,
 * a cashbook payment and a manual journal all reflect identically.
 *
 * Sign convention (matches WeConnectU): a supplier is a creditor, so the ledger
 * balance is NEGATIVE when we owe the supplier. The running balance is
 * accumulated as `balance += debit − credit`; a supplier CREDIT (invoice /
 * take-on) increases what we owe (more negative) and a supplier DEBIT (payment)
 * reduces it (toward zero). The stored Supplier::$balance is treated as the
 * take-on opening balance (already signed, negative = we owe).
 */
class DetailedSupplierLedgerService extends BaseService
{
    /**
     * @param SupplierLedgerService $ledger
     */
    public function __construct(private readonly SupplierLedgerService $ledger)
    {
        parent::__construct();
    }

    /**
     * Build the ledger for every selected supplier.
     *
     * @param Community $community
     * @param array $data
     * @return array{ledgers: array}
     */
    public function run(Community $community, array $data): array
    {
        $from     = $data['date_from'] ?? null;
        $to       = $data['date_to'] ?? null;
        $hideZero = filter_var($data['hide_zero'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ledgers = [];
        foreach ($this->resolveSuppliers($community, $data) as $supplier) {
            $ledger = $this->buildLedger($supplier, $from, $to);

            if ($hideZero && abs($ledger['totals']['balance']) < 0.005 && count($ledger['rows']) <= 1) {
                continue;
            }

            $ledgers[] = $ledger;
        }

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

        $filename = trim('detailed supplier ledger-' . strtolower((string) ($community->name ?? 'community')) . '-' . $from . ' to ' . $to);

        return Excel::download(
            new DetailedSupplierLedgerExport((string) $community->name, $from, $to, $ledgers),
            $filename . '.xlsx'
        );
    }

    /**
     * Resolve the suppliers to include based on the filters (supplier ids, group
     * ids, or "all"), ordered by supplier code like WeConnectU.
     *
     * @param Community $community
     * @param array $data
     * @return \Illuminate\Support\Collection<int, Supplier>
     */
    private function resolveSuppliers(Community $community, array $data): \Illuminate\Support\Collection
    {
        $query = Supplier::forCommunity($community->id);

        $supplierIds = array_filter((array) ($data['supplier_ids'] ?? []));
        $groupIds    = array_filter((array) ($data['group_ids'] ?? []));
        $all         = filter_var($data['all_suppliers'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $all && (! empty($supplierIds) || ! empty($groupIds))) {
            $query->where(function ($q) use ($supplierIds, $groupIds) {
                if (! empty($supplierIds)) {
                    $q->orWhereIn('id', $supplierIds);
                }
                if (! empty($groupIds)) {
                    $q->orWhereIn('supplier_group_id', $groupIds);
                }
            });
        }

        return $query->orderBy('supplier_code')->get();
    }

    /**
     * Build a single supplier's ledger.
     *
     * @param Supplier $supplier
     * @param string|null $from
     * @param string|null $to
     * @return array
     */
    private function buildLedger(Supplier $supplier, ?string $from, ?string $to): array
    {
        $events = $this->ledger->events($supplier->id, $supplier->community_id)->all();

        // Opening = take-on balance + every event strictly before the "from" date.
        $opening = (float) $supplier->balance;
        foreach ($events as $e) {
            if ($from && ($e['date'] ?? '') < $from) {
                $opening += $e['debit'] - $e['credit'];
            }
        }

        $inPeriod = array_values(array_filter($events, function ($e) use ($from, $to) {
            if ($from && ($e['date'] ?? '') < $from) {
                return false;
            }
            if ($to && ($e['date'] ?? '') > $to) {
                return false;
            }
            return true;
        }));

        usort($inPeriod, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        $balance     = $opening;
        $debitTotal  = 0.0;
        $creditTotal = 0.0;
        $rows        = [[
            'date'        => $from ?: ($inPeriod[0]['date'] ?? $to),
            'source'      => '',
            'description' => 'Balance b/f',
            'remarks'     => '',
            'debit'       => $opening > 0 ? round($opening, 2) : 0.0,
            'credit'      => $opening < 0 ? round(-$opening, 2) : 0.0,
            'balance'     => round($opening, 2),
        ]];

        foreach ($inPeriod as $e) {
            $balance     += $e['debit'] - $e['credit'];
            $debitTotal  += $e['debit'];
            $creditTotal += $e['credit'];
            $rows[]       = [
                'date'        => $e['date'],
                'source'      => $e['source'],
                'description' => $e['description'],
                'remarks'     => $e['remarks'],
                'debit'       => round($e['debit'], 2),
                'credit'      => round($e['credit'], 2),
                'balance'     => round($balance, 2),
            ];
        }

        return [
            'supplier_id'   => $supplier->id,
            'supplier_code' => $supplier->supplier_code,
            'supplier_name' => $supplier->name,
            'heading'       => trim(($supplier->supplier_code ? $supplier->supplier_code . ' - ' : '') . $supplier->name),
            'rows'          => $rows,
            'totals'        => [
                'debit'   => round($debitTotal, 2),
                'credit'  => round($creditTotal, 2),
                'balance' => round($balance, 2),
            ],
        ];
    }

}
