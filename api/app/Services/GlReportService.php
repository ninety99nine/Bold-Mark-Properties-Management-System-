<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\Community;
use App\Models\CommunityBudget;
use App\Models\JournalLine;
use App\Models\Ledger;
use Illuminate\Support\Collection;

/**
 * Read-only General Ledger reporting engine over journal_lines joined to
 * ledgers, scoped to a community (via journal_batches.community_id) and a
 * financial year / date range. Powers the six WeConnectU GL reports:
 * Trial Balance, Detailed General Ledger, Income Statement, Actual vs Budget
 * (incl. the Reserve Fund variant), VAT 201 and Basic Cash Movement.
 *
 * Sign convention: every general/reserve-fund journal line carries a positive
 * `amount` and an `entry_type` (debit|credit). A ledger's net over a period is
 * Σ(debit − credit). Debit-natural accounts (assets, expenses) carry a positive
 * net in their natural column; credit-natural accounts (income, liabilities,
 * equity) carry a negative net which is shown as a positive credit.
 */
class GlReportService extends BaseService
{
    /**
     * Financial categories whose natural balance sits on the DEBIT side
     * (assets and expenses). Everything else is credit-natural (income,
     * liabilities, equity).
     *
     * @var array<string>
     */
    private const DEBIT_NATURAL = [
        'current_assets', 'fixed_assets', 'other_fixed_assets', 'inventory',
        'investments', 'bank', 'accounts_receivable',
        'expenses', 'cost_of_sales', 'tax', 'taxation', 'dividends',
    ];

    /** Calendar-month column names on community_budgets, index 1..12. */
    private const MONTHS = [
        1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec',
    ];

    // -------------------------------------------------------------------------
    // 1. Trial Balance
    // -------------------------------------------------------------------------

    /**
     * Trial Balance — each account's net movement shown in its natural column;
     * total debits must equal total credits.
     *
     * @param Community $community
     * @param array $data  financial_year?, as_at?, hide_zero?, fund?
     * @return array{rows: array, totals: array{debit: float, credit: float}}
     */
    public function trialBalance(Community $community, array $data): array
    {
        $hideZero = $this->bool($data['hide_zero'] ?? true);
        $fund     = $data['fund'] ?? null;

        $nets = $this->ledgerNets(
            $community,
            financialYear: $data['financial_year'] ?? null,
            to: $data['as_at'] ?? null,
            fund: $fund,
        );

        $rows        = [];
        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($this->reportLedgers($community, $fund) as $ledger) {
            $net = round($nets[$ledger->id] ?? 0.0, 2);

            if ($hideZero && abs($net) < 0.005) {
                continue;
            }

            // net = Σ(debit − credit). Positive → debit column, negative → credit.
            $debit  = $net > 0 ? $net : 0.0;
            $credit = $net < 0 ? -$net : 0.0;

            $totalDebit  += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'ledger_id'          => $ledger->id,
                'code'               => $ledger->code,
                'name'               => $ledger->name,
                'financial_category' => $ledger->financial_category?->value,
                'debit'              => round($debit, 2),
                'credit'             => round($credit, 2),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));

        return [
            'rows'   => $rows,
            'totals' => [
                'debit'  => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 2. Detailed General Ledger
    // -------------------------------------------------------------------------

    /**
     * Detailed General Ledger — per account: opening (net before `from`), the
     * transactions in [from, to] with a running balance, and a closing balance.
     * All accounts when no ledger_id is supplied.
     *
     * @param Community $community
     * @param array $data  from?, to?, ledger_id?, financial_year?, hide_zero?, fund?
     * @return array{accounts: array}
     */
    public function generalLedger(Community $community, array $data): array
    {
        $from     = $data['from'] ?? null;
        $to       = $data['to'] ?? null;
        $ledgerId = $data['ledger_id'] ?? null;
        $hideZero = $this->bool($data['hide_zero'] ?? false);
        $fund     = $data['fund'] ?? null;

        $ledgers = $this->reportLedgers($community, $fund);
        if ($ledgerId) {
            $ledgers = $ledgers->where('id', $ledgerId)->values();
        }

        // Opening = net movement strictly before `from` (per ledger).
        $openings = $from
            ? $this->ledgerNets($community, financialYear: null, from: null, to: $this->dayBefore($from), fund: $fund)
            : [];

        // Transactions in the window.
        $lines = $this->linesQuery($community, $fund)
            ->when($from, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '<=', $to)))
            ->when($ledgerId, fn ($q) => $q->where('ledger_id', $ledgerId))
            ->with('batch:id,batch_number,date,source')
            ->get()
            ->groupBy('ledger_id');

        $accounts = [];

        foreach ($ledgers as $ledger) {
            $opening = round($openings[$ledger->id] ?? 0.0, 2);

            $txns    = collect($lines[$ledger->id] ?? [])
                ->sortBy(fn (JournalLine $l) => optional($l->batch?->date)->toDateString())
                ->values();

            if ($hideZero && $txns->isEmpty() && abs($opening) < 0.005) {
                continue;
            }

            $balance      = $opening;
            $rows         = [];
            $debitTotal   = 0.0;
            $creditTotal  = 0.0;

            foreach ($txns as $line) {
                $isDebit = $line->entry_type === JournalEntryType::DEBIT;
                $debit   = $isDebit ? (float) $line->amount : 0.0;
                $credit  = $isDebit ? 0.0 : (float) $line->amount;
                $balance += $debit - $credit;
                $debitTotal  += $debit;
                $creditTotal += $credit;

                $rows[] = [
                    'date'        => optional($line->batch?->date)->toDateString(),
                    'source'      => $this->sourceLabel($line),
                    'reference'   => $line->batch?->batch_number ? 'Journal Batch ' . $line->batch->batch_number : '',
                    'description' => (string) ($line->description ?? ''),
                    'debit'       => round($debit, 2),
                    'credit'      => round($credit, 2),
                    'balance'     => round($balance, 2),
                ];
            }

            $accounts[] = [
                'ledger'  => ['id' => $ledger->id, 'code' => $ledger->code, 'name' => $ledger->name],
                'opening' => $opening,
                'transactions' => $rows,
                'totals'  => ['debit' => round($debitTotal, 2), 'credit' => round($creditTotal, 2)],
                'closing' => round($balance, 2),
            ];
        }

        usort($accounts, fn ($a, $b) => strcmp((string) $a['ledger']['code'], (string) $b['ledger']['code']));

        return ['accounts' => $accounts];
    }

    // -------------------------------------------------------------------------
    // 3. Income Statement
    // -------------------------------------------------------------------------

    /**
     * Income Statement — income groups (sales / other_income) vs expense groups
     * (expenses / cost_of_sales), each with account rows, group subtotals, and a
     * Net Surplus/(Deficit). Reserve variant via fund=reserve.
     *
     * @param Community $community
     * @param array $data  financial_year?, from?, to?, fund?
     * @return array{income: array, expenses: array, totals: array}
     */
    public function incomeStatement(Community $community, array $data): array
    {
        $fund = $data['fund'] ?? 'main';

        $nets = $this->ledgerNets(
            $community,
            financialYear: $data['financial_year'] ?? null,
            from: $data['from'] ?? null,
            to: $data['to'] ?? null,
            fund: $fund,
        );

        $incomeCats  = ['sales', 'other_income'];
        $expenseCats = ['expenses', 'cost_of_sales'];

        $income   = $this->incomeStatementSection($community, $fund, $incomeCats, $nets, credit: true);
        $expenses = $this->incomeStatementSection($community, $fund, $expenseCats, $nets, credit: false);

        $totalIncome  = round(collect($income)->sum('subtotal'), 2);
        $totalExpense = round(collect($expenses)->sum('subtotal'), 2);
        $net          = round($totalIncome - $totalExpense, 2);

        return [
            'fund'     => $fund,
            'income'   => $income,
            'expenses' => $expenses,
            'totals'   => [
                'income'  => $totalIncome,
                'expense' => $totalExpense,
                'net'     => $net,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 4. Actual vs Budget (also Reserve Fund vs Reserve Fund Budget)
    // -------------------------------------------------------------------------

    /**
     * Actual vs Budget — per income/expense account: monthly actual (journal
     * lines grouped by calendar month) vs monthly budget (community_budgets
     * jan..dec), YTD actual, YTD budget, variance, total budget.
     *
     * @param Community $community
     * @param array $data  financial_year, fund?, from?, to?, hide_zero?
     * @return array{fund: string, year: int, income: array, expenses: array, totals: array}
     */
    public function actualVsBudget(Community $community, array $data): array
    {
        $fund     = $data['fund'] ?? 'main';
        $year     = (int) ($data['financial_year'] ?? now()->year);
        $hideZero = $this->bool($data['hide_zero'] ?? false);
        $from     = $data['from'] ?? null;
        $to       = $data['to'] ?? null;

        // Monthly actuals per ledger, keyed [ledger_id][month] = signed natural amount.
        $monthly = $this->monthlyActualsByLedger($community, $year, $fund, $from, $to);

        // Budgets keyed by ledger_id.
        $budgets = CommunityBudget::where('community_id', $community->id)
            ->where('year', $year)
            ->get()
            ->keyBy('ledger_id');

        $incomeCats  = ['sales', 'other_income'];
        $expenseCats = ['expenses', 'cost_of_sales'];

        $income = $this->budgetSection($community, $fund, $incomeCats, $monthly, $budgets, credit: true, hideZero: $hideZero);
        $expenses = $this->budgetSection($community, $fund, $expenseCats, $monthly, $budgets, credit: false, hideZero: $hideZero);

        return [
            'fund'     => $fund,
            'year'     => $year,
            'income'   => $income,
            'expenses' => $expenses,
            'totals'   => [
                'income'  => $this->sumSectionTotals($income),
                'expense' => $this->sumSectionTotals($expenses),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 5. VAT 201
    // -------------------------------------------------------------------------

    /**
     * VAT 201 — SARS-style summary of output tax vs input tax from the VAT
     * Control account, over [from, to]. Output VAT = credits to VAT Control
     * (VAT charged on sales); Input VAT = debits to VAT Control (VAT paid on
     * purchases). Net VAT payable = output − input.
     *
     * Simplification: this is a summary of GL postings to the VAT Control
     * account, not a per-transaction VAT201 field mapping (fields 1/1A/4/etc.).
     *
     * @param Community $community
     * @param array $data  from?, to?
     * @return array{from: ?string, to: ?string, output_vat: float, input_vat: float, net_vat: float, direction: string, note: string}
     */
    public function vat201(Community $community, array $data): array
    {
        $from = $data['from'] ?? null;
        $to   = $data['to'] ?? null;

        $vatLedgerIds = $this->reportLedgers($community, null)
            ->filter(fn (Ledger $l) => $l->financial_category === FinancialCategory::VAT_CONTROL)
            ->pluck('id')
            ->all();

        $output = 0.0; // credits to VAT control (VAT on sales)
        $input  = 0.0; // debits to VAT control (VAT on purchases)

        if (! empty($vatLedgerIds)) {
            $lines = $this->linesQuery($community, null)
                ->whereIn('ledger_id', $vatLedgerIds)
                ->when($from, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '>=', $from)))
                ->when($to, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '<=', $to)))
                ->get();

            foreach ($lines as $line) {
                if ($line->entry_type === JournalEntryType::CREDIT) {
                    $output += (float) $line->amount;
                } else {
                    $input += (float) $line->amount;
                }
            }
        }

        $net = round($output - $input, 2);

        return [
            'from'       => $from,
            'to'         => $to,
            'output_vat' => round($output, 2),
            'input_vat'  => round($input, 2),
            'net_vat'    => abs($net),
            'direction'  => $net >= 0 ? 'payable' : 'refundable',
            'note'       => 'Summary of GL postings to the VAT Control account. Output VAT = credits (VAT on sales); Input VAT = debits (VAT on purchases). This is a control-account summary, not a per-field SARS VAT201 mapping.',
        ];
    }

    // -------------------------------------------------------------------------
    // 6. Basic Cash Movement
    // -------------------------------------------------------------------------

    /**
     * Basic Cash Movement — per bank/cashbook ledger (financial_category=bank,
     * the 8000 ledgers): opening (net before `from`), receipts (debits),
     * payments (credits) and closing over [from, to].
     *
     * @param Community $community
     * @param array $data  from?, to?
     * @return array{banks: array, totals: array}
     */
    public function cashMovement(Community $community, array $data): array
    {
        $from = $data['from'] ?? null;
        $to   = $data['to'] ?? null;

        $banks = $this->reportLedgers($community, null)
            ->filter(fn (Ledger $l) => $l->financial_category === FinancialCategory::BANK)
            ->values();

        $openings = $from
            ? $this->ledgerNets($community, financialYear: null, from: null, to: $this->dayBefore($from), fund: null)
            : [];

        $lines = $this->linesQuery($community, null)
            ->whereIn('ledger_id', $banks->pluck('id')->all())
            ->when($from, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '<=', $to)))
            ->get()
            ->groupBy('ledger_id');

        $rows       = [];
        $totOpen    = 0.0;
        $totRecv    = 0.0;
        $totPay     = 0.0;
        $totClose   = 0.0;

        foreach ($banks as $bank) {
            $opening  = round($openings[$bank->id] ?? 0.0, 2);
            $receipts = 0.0;
            $payments = 0.0;

            foreach ($lines[$bank->id] ?? [] as $line) {
                if ($line->entry_type === JournalEntryType::DEBIT) {
                    $receipts += (float) $line->amount;
                } else {
                    $payments += (float) $line->amount;
                }
            }

            $closing = round($opening + $receipts - $payments, 2);

            $totOpen  += $opening;
            $totRecv  += $receipts;
            $totPay   += $payments;
            $totClose += $closing;

            $rows[] = [
                'ledger_id' => $bank->id,
                'code'      => $bank->code,
                'name'      => $bank->name,
                'opening'   => $opening,
                'receipts'  => round($receipts, 2),
                'payments'  => round($payments, 2),
                'closing'   => $closing,
            ];
        }

        return [
            'banks'  => $rows,
            'totals' => [
                'opening'  => round($totOpen, 2),
                'receipts' => round($totRecv, 2),
                'payments' => round($totPay, 2),
                'closing'  => round($totClose, 2),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Excel exports
    // -------------------------------------------------------------------------

    /**
     * Excel download for any report by key.
     *
     * @param string $report  trial-balance|general-ledger|income-statement|actual-vs-budget|vat-201|cash-movement
     * @param Community $community
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export(string $report, Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        [$rows, $headings] = match ($report) {
            'trial-balance'    => $this->trialBalanceExportRows($community, $data),
            'general-ledger'   => $this->generalLedgerExportRows($community, $data),
            'income-statement' => $this->incomeStatementExportRows($community, $data),
            'actual-vs-budget' => $this->actualVsBudgetExportRows($community, $data),
            'vat-201'          => $this->vat201ExportRows($community, $data),
            'cash-movement'    => $this->cashMovementExportRows($community, $data),
            default            => abort(404),
        };

        $name = \Illuminate\Support\Str::slug((string) ($community->name ?? 'community')) . '-' . $report;

        return $this->buildFileResponse($rows, $headings, $name, 'xlsx');
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function trialBalanceExportRows(Community $community, array $data): array
    {
        $report = $this->trialBalance($community, $data);
        $rows   = array_map(fn ($r) => [$r['code'], $r['name'], $r['financial_category'], $r['debit'], $r['credit']], $report['rows']);
        $rows[] = ['', 'TOTAL', '', $report['totals']['debit'], $report['totals']['credit']];

        return [$rows, ['Code', 'Account', 'Category', 'Debit', 'Credit']];
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function generalLedgerExportRows(Community $community, array $data): array
    {
        $report = $this->generalLedger($community, $data);
        $rows   = [];

        foreach ($report['accounts'] as $acc) {
            $rows[] = [$acc['ledger']['code'] . ' - ' . $acc['ledger']['name'], 'Opening Balance', '', '', '', $acc['opening']];
            foreach ($acc['transactions'] as $t) {
                $rows[] = [$t['date'], $t['source'], $t['reference'], $t['description'], $this->pair($t['debit'], $t['credit']), $t['balance']];
            }
            $rows[] = ['', 'Closing Balance', '', '', '', $acc['closing']];
        }

        return [$rows, ['Date / Account', 'Source', 'Reference', 'Description', 'Debit / Credit', 'Balance']];
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function incomeStatementExportRows(Community $community, array $data): array
    {
        $report = $this->incomeStatement($community, $data);
        $rows   = [['INCOME', '', '']];

        foreach ($report['income'] as $g) {
            $rows[] = [$g['name'], '', ''];
            foreach ($g['accounts'] as $a) {
                $rows[] = ['  ' . $a['code'], $a['name'], $a['actual']];
            }
            $rows[] = ['  Subtotal', '', $g['subtotal']];
        }
        $rows[] = ['Total Income', '', $report['totals']['income']];
        $rows[] = ['EXPENSES', '', ''];
        foreach ($report['expenses'] as $g) {
            $rows[] = [$g['name'], '', ''];
            foreach ($g['accounts'] as $a) {
                $rows[] = ['  ' . $a['code'], $a['name'], $a['actual']];
            }
            $rows[] = ['  Subtotal', '', $g['subtotal']];
        }
        $rows[] = ['Total Expenses', '', $report['totals']['expense']];
        $rows[] = ['Net Surplus/(Deficit)', '', $report['totals']['net']];

        return [$rows, ['Code / Section', 'Account', 'Actual']];
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function actualVsBudgetExportRows(Community $community, array $data): array
    {
        $report   = $this->actualVsBudget($community, $data);
        $rows     = [];

        foreach (['income' => 'INCOME', 'expenses' => 'EXPENSES'] as $key => $label) {
            $rows[] = [$label, '', '', '', '', ''];
            foreach ($report[$key] as $g) {
                $rows[] = [$g['name'], '', '', '', '', ''];
                foreach ($g['accounts'] as $a) {
                    $rows[] = [$a['code'], $a['name'], $a['ytd_actual'], $a['ytd_budget'], $a['variance'], $a['total_budget']];
                }
            }
        }

        return [$rows, ['Code', 'Account', 'YTD Actual', 'YTD Budget', 'Variance', 'Total Budget']];
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function vat201ExportRows(Community $community, array $data): array
    {
        $report = $this->vat201($community, $data);
        $rows   = [
            ['Output VAT (VAT on sales)', $report['output_vat']],
            ['Input VAT (VAT on purchases)', $report['input_vat']],
            ['Net VAT ' . $report['direction'], $report['net_vat']],
        ];

        return [$rows, ['Description', 'Amount']];
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function cashMovementExportRows(Community $community, array $data): array
    {
        $report = $this->cashMovement($community, $data);
        $rows   = array_map(
            fn ($b) => [$b['code'], $b['name'], $b['opening'], $b['receipts'], $b['payments'], $b['closing']],
            $report['banks']
        );
        $t      = $report['totals'];
        $rows[] = ['', 'TOTAL', $t['opening'], $t['receipts'], $t['payments'], $t['closing']];

        return [$rows, ['Code', 'Bank / Cashbook', 'Opening', 'Receipts', 'Payments', 'Closing']];
    }

    /**
     * Render a debit/credit pair as a single cell for the flat GL export.
     *
     * @param float $debit
     * @param float $credit
     * @return string
     */
    private function pair(float $debit, float $credit): string
    {
        if ($debit > 0) {
            return number_format($debit, 2) . ' Dr';
        }
        if ($credit > 0) {
            return number_format($credit, 2) . ' Cr';
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // Shared internals
    // -------------------------------------------------------------------------

    /**
     * The general/reserve-fund ledgers that appear on GL reports for a
     * community's organisation — leaf accounts only (skip the X000/000 headers),
     * optionally filtered by fund.
     *
     * @param Community $community
     * @param string|null $fund  main|reserve|null (all)
     * @return Collection<int, Ledger>
     */
    private function reportLedgers(Community $community, ?string $fund): Collection
    {
        // Every postable account — exclude only the "X000/000" group headers,
        // which never receive postings directly (their sub-accounts do). This
        // includes the control accounts (Accounts Receivable / Payable / VAT /
        // Bank / Suspense) into which the customer & supplier subledgers roll up.
        return Ledger::where('organization_id', $community->organization_id)
            ->where('code', 'not like', '%/000')
            ->when($fund, fn ($q) => $q->where('fund', $fund))
            ->orderBy('code')
            ->get();
    }

    /**
     * Base journal-line query for a community's general-ledger lines (lines that
     * carry a ledger_id — general + reserve_fund). Scoped by batch community and,
     * optionally, by the ledger's fund.
     *
     * @param Community $community
     * @param string|null $fund
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function linesQuery(Community $community, ?string $fund)
    {
        // Only general + reserve-fund lines are grouped by ledger_id. Customer and
        // supplier lines are rolled up into their control accounts separately (see
        // ledgerNets), so they are excluded here even if they carry a stray
        // ledger_id — that prevents double counting the AR/AP control balances.
        return JournalLine::query()
            ->whereNotNull('ledger_id')
            ->whereIn('line_type', [JournalLineType::GENERAL->value, JournalLineType::RESERVE_FUND->value])
            ->whereHas('batch', fn ($b) => $b->where('community_id', $community->id))
            ->when($fund, fn ($q) => $q->whereHas('ledger', fn ($l) => $l->where('fund', $fund)));
    }

    /**
     * Net movement Σ(debit − credit) per ledger id, scoped to a community and a
     * financial year and/or a [from, to] date window.
     *
     * @param Community $community
     * @param int|null $financialYear
     * @param string|null $from
     * @param string|null $to
     * @param string|null $fund
     * @return array<string, float>  ledger_id => net
     */
    private function ledgerNets(
        Community $community,
        ?int $financialYear = null,
        ?string $from = null,
        ?string $to = null,
        ?string $fund = null,
    ): array {
        $lines = $this->linesQuery($community, $fund)
            ->when($financialYear, fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('financial_year', $financialYear)))
            ->when($from, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '<=', $to)))
            ->get(['id', 'ledger_id', 'entry_type', 'amount']);

        $nets = [];
        foreach ($lines as $line) {
            $signed = $line->entry_type === JournalEntryType::DEBIT ? (float) $line->amount : -(float) $line->amount;
            $nets[$line->ledger_id] = ($nets[$line->ledger_id] ?? 0.0) + $signed;
        }

        // Roll the customer subledger into the Accounts Receivable control account
        // and the supplier subledger into Accounts Payable — those lines carry a
        // unit_id / supplier_id (not a ledger_id), so without this the Trial
        // Balance would omit AR/AP and fail to tie out. Both are main-fund control
        // accounts, so skip when reporting on the reserve fund only.
        if ($fund === null || $fund === 'main') {
            $controls = [
                'customer' => Ledger::controlAccount($community->organization_id, FinancialCategory::ACCOUNTS_RECEIVABLE),
                'supplier' => Ledger::controlAccount($community->organization_id, FinancialCategory::ACCOUNTS_PAYABLE),
            ];

            foreach ($controls as $lineType => $control) {
                if (! $control) {
                    continue;
                }
                $net = $this->subledgerNet($community, $lineType, $financialYear, $from, $to);
                if ($net != 0.0) {
                    $nets[$control->id] = ($nets[$control->id] ?? 0.0) + $net;
                }
            }
        }

        return $nets;
    }

    /**
     * Net movement Σ(debit − credit) for a subledger line type (customer|supplier)
     * — lines that carry a unit_id / supplier_id rather than a ledger_id — scoped
     * to a community + optional financial year / date window. Used to roll the
     * customer & supplier subledgers into their control accounts on GL reports.
     *
     * @param Community $community
     * @param string $lineType  customer|supplier
     * @param int|null $financialYear
     * @param string|null $from
     * @param string|null $to
     * @return float
     */
    private function subledgerNet(
        Community $community,
        string $lineType,
        ?int $financialYear,
        ?string $from,
        ?string $to,
    ): float {
        $lines = JournalLine::query()
            ->where('line_type', $lineType)
            ->whereHas('batch', fn ($b) => $b->where('community_id', $community->id)
                ->when($financialYear, fn ($q) => $q->where('financial_year', $financialYear))
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to)))
            ->get(['entry_type', 'amount']);

        $net = 0.0;
        foreach ($lines as $line) {
            $net += $line->entry_type === JournalEntryType::DEBIT ? (float) $line->amount : -(float) $line->amount;
        }

        return $net;
    }

    /**
     * Monthly actuals per ledger for a fund + financial year, as the natural
     * signed amount (income shown positive, expenses shown positive).
     *
     * @return array<string, array<int, float>>  ledger_id => [month => amount]
     */
    private function monthlyActualsByLedger(
        Community $community,
        int $year,
        string $fund,
        ?string $from,
        ?string $to,
    ): array {
        $lines = $this->linesQuery($community, $fund)
            ->whereHas('batch', fn ($b) => $b->where('financial_year', $year))
            ->when($from, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('batch', fn ($b) => $b->whereDate('date', '<=', $to)))
            ->with('batch:id,date')
            ->get();

        $out = [];
        foreach ($lines as $line) {
            $date = $line->batch?->date;
            if (! $date) {
                continue;
            }
            $month = (int) $date->format('n');
            $net   = $line->entry_type === JournalEntryType::DEBIT ? (float) $line->amount : -(float) $line->amount;

            $out[$line->ledger_id] ??= array_fill(1, 12, 0.0);
            $out[$line->ledger_id][$month] += $net;
        }

        return $out;
    }

    /**
     * Build an Income Statement section (income or expenses), grouped by the
     * ledger's MAIN account (parent), each with account rows + a subtotal.
     *
     * @param array<string> $categories
     * @param array<string, float> $nets
     * @param bool $credit  income sections are credit-natural
     * @return array<int, array>
     */
    private function incomeStatementSection(Community $community, string $fund, array $categories, array $nets, bool $credit): array
    {
        $ledgers = $this->reportLedgers($community, $fund)
            ->filter(fn (Ledger $l) => in_array($l->financial_category?->value, $categories, true));

        $groups = [];
        foreach ($ledgers as $ledger) {
            $net    = $nets[$ledger->id] ?? 0.0;
            // Income accounts are credit-natural → flip sign so income is positive.
            $actual = round($credit ? -$net : $net, 2);

            $parentId = $ledger->parent_id ?? $ledger->id;
            $groups[$parentId] ??= [
                'name'     => $ledger->parent?->name ?? $ledger->name,
                'code'     => $ledger->parent?->code ?? $ledger->code,
                'accounts' => [],
                'subtotal' => 0.0,
            ];

            $groups[$parentId]['accounts'][] = [
                'code'   => $ledger->code,
                'name'   => $ledger->name,
                'actual' => $actual,
            ];
            $groups[$parentId]['subtotal'] += $actual;
        }

        $result = array_values($groups);
        foreach ($result as &$g) {
            $g['subtotal'] = round($g['subtotal'], 2);
            usort($g['accounts'], fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));
        }
        usort($result, fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));

        return $result;
    }

    /**
     * Build an Actual-vs-Budget section (income or expenses), grouped by MAIN
     * account, each account carrying monthly actual/budget arrays + YTD +
     * variance + total budget.
     *
     * @param array<string> $categories
     * @param array<string, array<int, float>> $monthly
     * @param Collection<string, CommunityBudget> $budgets
     * @param bool $credit
     * @param bool $hideZero
     * @return array<int, array>
     */
    private function budgetSection(
        Community $community,
        string $fund,
        array $categories,
        array $monthly,
        Collection $budgets,
        bool $credit,
        bool $hideZero,
    ): array {
        $ledgers = $this->reportLedgers($community, $fund)
            ->filter(fn (Ledger $l) => in_array($l->financial_category?->value, $categories, true));

        $groups = [];
        foreach ($ledgers as $ledger) {
            $monthActuals = [];
            $ytdActual    = 0.0;
            foreach (self::MONTHS as $m => $col) {
                $raw = $monthly[$ledger->id][$m] ?? 0.0;
                $val = round($credit ? -$raw : $raw, 2);
                $monthActuals[$col] = $val;
                $ytdActual += $val;
            }

            $budget      = $budgets->get($ledger->id);
            $monthBudget = [];
            $ytdBudget   = 0.0;
            foreach (self::MONTHS as $m => $col) {
                $val = round((float) ($budget?->{$col} ?? 0.0), 2);
                $monthBudget[$col] = $val;
                $ytdBudget += $val;
            }
            $totalBudget = round((float) ($budget?->per_year ?? $ytdBudget), 2);

            $ytdActual = round($ytdActual, 2);
            $ytdBudget = round($ytdBudget, 2);

            if ($hideZero && abs($ytdActual) < 0.005 && abs($totalBudget) < 0.005) {
                continue;
            }

            $parentId = $ledger->parent_id ?? $ledger->id;
            $groups[$parentId] ??= [
                'name'     => $ledger->parent?->name ?? $ledger->name,
                'code'     => $ledger->parent?->code ?? $ledger->code,
                'accounts' => [],
                'subtotal' => ['ytd_actual' => 0.0, 'ytd_budget' => 0.0, 'variance' => 0.0, 'total_budget' => 0.0],
            ];

            $groups[$parentId]['accounts'][] = [
                'code'          => $ledger->code,
                'name'          => $ledger->name,
                'actual'        => $monthActuals,
                'budget'        => $monthBudget,
                'ytd_actual'    => $ytdActual,
                'ytd_budget'    => $ytdBudget,
                'variance'      => round($ytdActual - $ytdBudget, 2),
                'total_budget'  => $totalBudget,
            ];
            $groups[$parentId]['subtotal']['ytd_actual']   += $ytdActual;
            $groups[$parentId]['subtotal']['ytd_budget']   += $ytdBudget;
            $groups[$parentId]['subtotal']['variance']     += $ytdActual - $ytdBudget;
            $groups[$parentId]['subtotal']['total_budget'] += $totalBudget;
        }

        $result = array_values($groups);
        foreach ($result as &$g) {
            foreach ($g['subtotal'] as $k => $v) {
                $g['subtotal'][$k] = round($v, 2);
            }
            usort($g['accounts'], fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));
        }
        usort($result, fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));

        return $result;
    }

    /**
     * Sum a budget section's group subtotals into overall totals.
     *
     * @param array<int, array> $section
     * @return array{ytd_actual: float, ytd_budget: float, variance: float, total_budget: float}
     */
    private function sumSectionTotals(array $section): array
    {
        return [
            'ytd_actual'   => round(collect($section)->sum(fn ($g) => $g['subtotal']['ytd_actual']), 2),
            'ytd_budget'   => round(collect($section)->sum(fn ($g) => $g['subtotal']['ytd_budget']), 2),
            'variance'     => round(collect($section)->sum(fn ($g) => $g['subtotal']['variance']), 2),
            'total_budget' => round(collect($section)->sum(fn ($g) => $g['subtotal']['total_budget']), 2),
        ];
    }

    /**
     * WeConnectU-style source label for a journal line (batch source / batch name).
     *
     * @param JournalLine $line
     * @return string
     */
    private function sourceLabel(JournalLine $line): string
    {
        $batch = $line->batch;
        if (! $batch) {
            return 'Journal';
        }

        if ($batch->batch_number) {
            return 'Journal Batch ' . $batch->batch_number;
        }

        return $batch->source instanceof \App\Enums\JournalSource ? $batch->source->label() : 'Journal';
    }

    /**
     * The day before a Y-m-d date (for "strictly before" opening windows).
     *
     * @param string $date
     * @return string
     */
    private function dayBefore(string $date): string
    {
        return \Carbon\Carbon::parse($date)->subDay()->toDateString();
    }

    /**
     * Coerce a truthy request value to a boolean.
     *
     * @param mixed $value
     * @return bool
     */
    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
