<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\Community;
use App\Models\CommunityBudget;
use App\Models\CommunityBudgetLock;
use App\Imports\RowsImport;
use App\Exports\SimpleExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * WeConnectU "Budget" tab + "Setup Reserve Fund Budget".
 *
 * Reads/writes the per-community, per-ledger, per-year monthly budget grid over
 * community_budgets (main fund = income + expense ledgers; reserve fund = RFI/RFE),
 * supports Equal Monthly split, budget locking, and Excel template/import.
 */
class BudgetService extends BaseService
{
    /** Calendar month column keys in order. */
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    /**
     * Build the budget grid for a community, fund and year: every ledger of the
     * fund with its budget row (0s when none captured yet) + lock status.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function showBudgets(Community $community, array $data): array
    {
        $orgId = Auth::user()->organization_id;
        $fund  = $data['fund'] ?? 'main';
        $year  = (int) ($data['year'] ?? Carbon::today()->year);

        $ledgers = $this->fundLedgers($orgId, $fund);

        $budgets = CommunityBudget::where('community_id', $community->id)
            ->where('year', $year)
            ->get()
            ->keyBy('ledger_id');

        $rows = $ledgers->map(function (Ledger $ledger) use ($budgets): array {
            /** @var CommunityBudget|null $budget */
            $budget = $budgets->get($ledger->id);

            $months = [];
            foreach (self::MONTHS as $m) {
                $months[$m] = $budget ? (float) $budget->{$m} : 0.0;
            }

            $distinct     = array_unique(array_map(fn ($v) => round($v, 2), array_values($months)));
            $equalMonthly = count($distinct) === 1 && $distinct[array_key_first($distinct)] > 0;

            return array_merge([
                'ledger_id'          => $ledger->id,
                'code'               => $ledger->code,
                'name'               => $ledger->name,
                'category'           => $ledger->category,
                'account_type'       => $ledger->account_type,
                'financial_category' => $ledger->financial_category?->value,
                'fund'               => $ledger->fund,
            ], $months, [
                'per_year'      => $budget ? (float) $budget->per_year : 0.0,
                'equal_monthly' => $equalMonthly,
            ]);
        })->values()->all();

        return [
            'data'   => $rows,
            'year'   => $year,
            'fund'   => $fund,
            'locked' => $this->isLocked($community, $year, $fund),
        ];
    }

    /**
     * Upsert budget rows for a community, fund and year. Rejected when the period
     * is locked. When a row is flagged equal_monthly, each month = per_year / 12
     * (or, if only monthly values were sent, per_year = 12 x the month).
     *
     * @param Community $community
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function upsertBudgets(Community $community, array $data): array
    {
        $orgId = Auth::user()->organization_id;
        $fund  = $data['fund'] ?? 'main';
        $year  = (int) $data['year'];

        if ($this->isLocked($community, $year, $fund)) {
            throw ValidationException::withMessages([
                'year' => 'This budget period is locked. Re-open it for editing first.',
            ]);
        }

        $allowedLedgerIds = $this->fundLedgers($orgId, $fund)->pluck('id')->all();

        DB::transaction(function () use ($data, $community, $orgId, $year, $allowedLedgerIds) {
            foreach ($data['rows'] as $row) {
                if (!in_array($row['ledger_id'], $allowedLedgerIds, true)) {
                    continue;
                }

                $months  = $this->resolveMonths($row);
                $perYear = round(array_sum($months), 2);

                CommunityBudget::updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'ledger_id'    => $row['ledger_id'],
                        'year'         => $year,
                    ],
                    array_merge($months, [
                        'per_year'        => $perYear,
                        'organization_id' => $orgId,
                    ])
                );
            }
        });

        return $this->showBudgets($community, ['fund' => $fund, 'year' => $year]);
    }

    /**
     * Lock a budget period (fund + year) so it can no longer be edited.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function lockBudget(Community $community, array $data): array
    {
        return $this->setLock($community, $data, true);
    }

    /**
     * Re-open a budget period for editing.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function unlockBudget(Community $community, array $data): array
    {
        return $this->setLock($community, $data, false);
    }

    /**
     * Download the budget template (xlsx) — one row per ledger of the fund with
     * empty monthly columns, ready to be filled and re-imported.
     *
     * @param Community $community
     * @param array $data
     * @return Response
     */
    public function downloadTemplate(Community $community, array $data): Response
    {
        $orgId = Auth::user()->organization_id;
        $fund  = $data['fund'] ?? 'main';

        $rows = $this->fundLedgers($orgId, $fund)->map(fn (Ledger $ledger): array => array_merge([
            $ledger->code,
            $ledger->name,
        ], array_fill(0, 12, 0)))->all();

        $headings = array_merge(['Account', 'Description'], array_map('ucfirst', self::MONTHS));

        return $this->buildFileResponse(
            $rows,
            $headings,
            'budget-template-' . $fund . '-' . $community->id,
            'xlsx'
        );
    }

    /**
     * Import a filled budget spreadsheet. Rows are matched to ledgers by account
     * code (column 1); the 12 month columns (3..14) become the monthly budget.
     *
     * @param Community $community
     * @param array $data
     * @param UploadedFile $file
     * @return array
     * @throws ValidationException
     */
    public function importBudget(Community $community, array $data, UploadedFile $file): array
    {
        $orgId = Auth::user()->organization_id;
        $fund  = $data['fund'] ?? 'main';
        $year  = (int) $data['year'];

        if ($this->isLocked($community, $year, $fund)) {
            throw ValidationException::withMessages([
                'year' => 'This budget period is locked. Re-open it for editing first.',
            ]);
        }

        $sheets = Excel::toArray(new RowsImport, $file);
        $rows   = $sheets[0] ?? [];

        // Map account code → ledger id for this fund.
        $ledgers = $this->fundLedgers($orgId, $fund)->keyBy('code');

        $imported = 0;

        DB::transaction(function () use ($rows, $ledgers, $community, $orgId, $year, &$imported) {
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue; // heading row
                }

                $code = trim((string) ($row[0] ?? ''));
                if ($code === '' || !$ledgers->has($code)) {
                    continue;
                }

                $months = [];
                foreach (self::MONTHS as $i => $key) {
                    $months[$key] = round((float) ($row[$i + 2] ?? 0), 2);
                }

                CommunityBudget::updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'ledger_id'    => $ledgers->get($code)->id,
                        'year'         => $year,
                    ],
                    array_merge($months, [
                        'per_year'        => round(array_sum($months), 2),
                        'organization_id' => $orgId,
                    ])
                );

                $imported++;
            }
        });

        return [
            'message'  => "{$imported} budget rows imported",
            'imported' => $imported,
        ];
    }

    /**
     * The ledgers that make up a fund's budget grid.
     * main    → income + expense (income_statement) accounts, main fund.
     * reserve → RFI / RFE accounts.
     *
     * @param string $orgId
     * @param string $fund
     * @return \Illuminate\Support\Collection
     */
    protected function fundLedgers(string $orgId, string $fund): \Illuminate\Support\Collection
    {
        return Ledger::query()
            ->where('organization_id', $orgId)
            ->where('fund', $fund)
            ->when($fund === 'main', fn ($q) => $q->where('account_type', 'income_statement'))
            ->orderBy('code')
            ->get();
    }

    /**
     * Resolve the 12 monthly amounts for an upsert row, honouring equal_monthly.
     *
     * @param array $row
     * @return array<string,float>
     */
    protected function resolveMonths(array $row): array
    {
        $equalMonthly = (bool) ($row['equal_monthly'] ?? false);

        if ($equalMonthly) {
            $per = null;
            if (isset($row['per_year'])) {
                $per = round(((float) $row['per_year']) / 12, 2);
            } elseif (isset($row['jan'])) {
                $per = round((float) $row['jan'], 2);
            } else {
                $per = 0.0;
            }

            return array_fill_keys(self::MONTHS, $per);
        }

        $months = [];
        foreach (self::MONTHS as $m) {
            $months[$m] = round((float) ($row[$m] ?? 0), 2);
        }

        return $months;
    }

    /**
     * Whether a budget period (fund + year) is locked.
     *
     * @param Community $community
     * @param int $year
     * @param string $fund
     * @return bool
     */
    protected function isLocked(Community $community, int $year, string $fund): bool
    {
        return CommunityBudgetLock::where('community_id', $community->id)
            ->where('year', $year)
            ->where('fund', $fund)
            ->whereNotNull('locked_at')
            ->exists();
    }

    /**
     * Set or clear the lock for a budget period.
     *
     * @param Community $community
     * @param array $data
     * @param bool $locked
     * @return array
     */
    protected function setLock(Community $community, array $data, bool $locked): array
    {
        $orgId = Auth::user()->organization_id;
        $fund  = $data['fund'] ?? 'main';
        $year  = (int) $data['year'];

        CommunityBudgetLock::updateOrCreate(
            [
                'community_id' => $community->id,
                'year'         => $year,
                'fund'         => $fund,
            ],
            [
                'locked_at'       => $locked ? Carbon::now() : null,
                'organization_id' => $orgId,
            ]
        );

        return [
            'message' => $locked ? 'Budget locked' : 'Budget re-opened for editing',
            'locked'  => $locked,
            'year'    => $year,
            'fund'    => $fund,
        ];
    }
}
