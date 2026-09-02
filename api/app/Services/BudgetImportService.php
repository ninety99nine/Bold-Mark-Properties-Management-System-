<?php

namespace App\Services;

use Exception;
use App\Enums\LedgerAppliesTo;
use App\Enums\FinancialCategory;
use App\Models\Community;
use App\Models\CommunityBudget;
use App\Models\Ledger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The single source of truth for the WeConnectU "Budget" spreadsheet — the file
 * behind every "Download Budget Template" / "Click here to download the budget
 * template" button (take-on page, Budget Setup page, and the Budget Excel Import
 * modal all stream the identical file) and the parser behind every upload.
 *
 * The template is built dynamically from the community's actual chart of accounts
 * so it reflects any custom ledgers, and it is pre-filled with the currently
 * captured budget (0.00 everywhere for a brand-new community). Layout — byte
 * faithful to WeConnectU:
 *
 *   A1: "Actual Budget"   B1: "YYYY-01-01 to YYYY-12-31"
 *   A2: <community name>
 *   (blank)
 *   A4: ""  B4..M4: Jan..Dec   N4: "Per Year"
 *   A5: ""  B5..M5: <year>      N5: " "
 *   A6: "TOTAL INCOME"
 *        1000/000 - INCOME               (group header = sum of its children)
 *        1000/001 - Levies …             (leaf accounts)
 *   (blank)
 *   "TOTAL EXPENSES"
 *        2000/000 - ADMINISTRATIVE EXPENSES …
 */
class BudgetImportService extends BaseService
{
    /**
     * Ordered month keys matching the 12 columns (B–M) of the WeConnectU budget.
     */
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    /**
     * Stream the WeConnectU-format budget spreadsheet for a community, fund and
     * year — one row per chart-of-accounts line (income first under TOTAL INCOME,
     * then expenses under TOTAL EXPENSES), pre-filled with the captured budget.
     *
     * @param Community $community
     * @param string $fund   'main' | 'reserve'
     * @param int|null $year  defaults to the current calendar year
     * @return StreamedResponse
     */
    public function downloadTemplate(Community $community, string $fund = 'main', ?int $year = null): StreamedResponse
    {
        $year = $year ?: (int) date('Y');

        $ledgers = $this->fundLedgers($community->organization_id, $fund);
        $budgets = CommunityBudget::where('community_id', $community->id)
            ->where('year', $year)
            ->get()
            ->keyBy('ledger_id');

        // Pre-compute each parent's monthly aggregate from its children's budgets.
        $childTotals = $this->aggregateByParent($ledgers, $budgets);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Worksheet');

        // Rows 1–2: title + date range, then community name.
        $sheet->setCellValue('A1', 'Actual Budget');
        $sheet->setCellValue('B1', "{$year}-01-01 to {$year}-12-31");
        $sheet->setCellValue('A2', $community->name);

        // Row 4: month headers (B–M) + Per Year (N). Row 5: the year under each month.
        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $col = 'B';
        foreach ($monthLabels as $label) {
            $sheet->setCellValue($col . '4', $label);
            $sheet->setCellValue($col . '5', $year);
            $col++;
        }
        $sheet->setCellValue('N4', 'Per Year');
        $sheet->setCellValue('N5', ' ');
        $sheet->getStyle('B4:N5')->getAlignment()->setHorizontal('right');

        // Column widths: A=30, B–N=20 (matches WeConnectU).
        $sheet->getColumnDimension('A')->setWidth(30);
        foreach (range('B', 'N') as $c) {
            $sheet->getColumnDimension($c)->setWidth(20);
        }

        // Split the fund's chart into income vs expense, preserving code order.
        [$income, $expense] = $ledgers->partition(fn (Ledger $l) => $this->isIncomeSide($l));

        // TOTAL INCOME section, then a blank row, then TOTAL EXPENSES section.
        $rowNum = 6;
        $sheet->setCellValue('A' . $rowNum, 'TOTAL INCOME');
        $rowNum = $this->writeLedgerRows($sheet, $income, $budgets, $childTotals, $rowNum + 1);

        $rowNum++; // blank spacer row
        $sheet->setCellValue('A' . $rowNum, 'TOTAL EXPENSES');
        $this->writeLedgerRows($sheet, $expense, $budgets, $childTotals, $rowNum + 1);

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'budget-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Write a block of ledger rows (label "code - name" in A, twelve months + the
     * per-year total in B–N). Parent (/000) rows carry the sum of their children;
     * leaf rows carry their own captured budget (0.00 when none). Returns the next
     * free row number.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param Collection $ledgers
     * @param Collection $budgets       ledger_id => CommunityBudget
     * @param array $childTotals        parent ledger_id => ['months' => [...], 'per_year' => float]
     * @param int $rowNum
     * @return int
     */
    private function writeLedgerRows($sheet, Collection $ledgers, Collection $budgets, array $childTotals, int $rowNum): int
    {
        foreach ($ledgers as $ledger) {
            $isParent = $ledger->parent_id === null;

            if ($isParent) {
                $months  = $childTotals[$ledger->id]['months'] ?? array_fill_keys(self::MONTHS, 0.0);
                $perYear = $childTotals[$ledger->id]['per_year'] ?? 0.0;
            } else {
                $budget = $budgets->get($ledger->id);
                $months = [];
                foreach (self::MONTHS as $m) {
                    $months[$m] = $budget ? (float) $budget->{$m} : 0.0;
                }
                $perYear = $budget ? (float) $budget->per_year : 0.0;
            }

            $sheet->setCellValue('A' . $rowNum, $ledger->code . ' - ' . $ledger->name);

            $col = 'B';
            foreach (self::MONTHS as $m) {
                $sheet->setCellValue($col . $rowNum, round($months[$m], 2));
                $col++;
            }
            $sheet->setCellValue('N' . $rowNum, round($perYear, 2));
            $sheet->getStyle('B' . $rowNum . ':N' . $rowNum)->getAlignment()->setHorizontal('right');

            $rowNum++;
        }

        return $rowNum;
    }

    /**
     * Sum each parent ledger's children budgets into a monthly + per-year total,
     * so the /000 group rows in the template mirror the grid's computed headers.
     *
     * @param Collection $ledgers
     * @param Collection $budgets  ledger_id => CommunityBudget
     * @return array<string, array{months: array<string,float>, per_year: float}>
     */
    private function aggregateByParent(Collection $ledgers, Collection $budgets): array
    {
        $totals = [];

        foreach ($ledgers as $ledger) {
            if ($ledger->parent_id === null) {
                continue;
            }

            $budget = $budgets->get($ledger->id);
            if (!$budget) {
                continue;
            }

            $parentId = $ledger->parent_id;
            if (!isset($totals[$parentId])) {
                $totals[$parentId] = ['months' => array_fill_keys(self::MONTHS, 0.0), 'per_year' => 0.0];
            }

            foreach (self::MONTHS as $m) {
                $totals[$parentId]['months'][$m] += (float) $budget->{$m};
            }
            $totals[$parentId]['per_year'] += (float) $budget->per_year;
        }

        return $totals;
    }

    /**
     * The fund's chart of accounts (parents + leaves) in code order — income
     * statement accounts for the main fund, the RFI/RFE chart for the reserve fund.
     *
     * @param string $organizationId
     * @param string $fund
     * @return Collection<int,Ledger>
     */
    private function fundLedgers(string $organizationId, string $fund): Collection
    {
        return Ledger::query()
            ->where('organization_id', $organizationId)
            ->where('fund', $fund)
            ->when($fund === 'main', fn ($q) => $q->where('account_type', 'income_statement'))
            ->orderBy('code')
            ->get();
    }

    /**
     * Whether a ledger sits on the income side of the budget (TOTAL INCOME block)
     * rather than the expense side (TOTAL EXPENSES block).
     *
     * @param Ledger $ledger
     * @return bool
     */
    private function isIncomeSide(Ledger $ledger): bool
    {
        return in_array($ledger->financial_category, [
            FinancialCategory::SALES,
            FinancialCategory::OTHER_INCOME,
        ], true);
    }

    /**
     * Parse an uploaded budget into a preview: per-ledger monthly figures, which
     * ledgers already exist vs. would be created, and a summary. No persistence.
     *
     * @param Community $community
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    public function parse(Community $community, mixed $file): array
    {
        [$year, $lines, $errors] = $this->extractLines($file);

        $existingCodes = Ledger::where('organization_id', $community->organization_id)
            ->whereNotNull('code')
            ->pluck('code')
            ->map(fn ($c) => strtolower($c))
            ->flip()
            ->toArray();

        $newLedgers = 0;
        foreach ($lines as &$line) {
            $line['exists'] = isset($existingCodes[strtolower($line['code'])]);
            if (!$line['exists']) {
                $newLedgers++;
            }
        }
        unset($line);

        return [
            'year'    => $year,
            'lines'   => $lines,
            'errors'  => $errors,
            'summary' => [
                'year'            => $year,
                'lines'           => count($lines),
                'new_ledgers'     => $newLedgers,
                'error_count'     => count($errors),
            ],
        ];
    }

    /**
     * Re-parse the file and commit the budget: match/create ledgers by code, then
     * upsert one community_budgets row per ledger for the year. Idempotent.
     *
     * @param Community $community
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    public function import(Community $community, mixed $file): array
    {
        [$year, $lines, $errors] = $this->extractLines($file);

        $importedLines = 0;
        $createdLedgers = 0;

        DB::transaction(function () use ($lines, $community, $year, &$importedLines, &$createdLedgers) {
            foreach ($lines as $line) {
                $ledger = Ledger::where('organization_id', $community->organization_id)
                    ->where('code', $line['code'])
                    ->first();

                if (!$ledger) {
                    $ledger = Ledger::create([
                        'code'            => $line['code'],
                        'category'        => $line['category'],
                        'name'            => $line['name'],
                        'is_system'       => false,
                        'is_active'       => true,
                        'is_recurring'    => true,
                        'applies_to'      => LedgerAppliesTo::EITHER,
                        'organization_id' => $community->organization_id,
                    ]);
                    $createdLedgers++;
                }

                CommunityBudget::updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'ledger_id'    => $ledger->id,
                        'year'         => $year,
                    ],
                    array_merge($line['months'], [
                        'per_year'        => $line['per_year'],
                        'organization_id' => $community->organization_id,
                    ]),
                );
                $importedLines++;
            }
        });

        // Store the raw file + mark the take-on "Budget" step as uploaded.
        (new CommunityTakeonService())->recordUpload($community, 'budget', $file);

        return [
            'year'            => $year,
            'imported_lines'  => $importedLines,
            'created_ledgers' => $createdLedgers,
            'error_count'     => count($errors),
            'errors'          => $errors,
            'message'         => "{$importedLines} budget lines imported for {$year} ({$createdLedgers} new ledgers created).",
        ];
    }

    /**
     * Parse a WeConnectU budget file into [year, lines] for callers that upsert
     * into an existing chart of accounts (the Budget Setup page import) without
     * creating ledgers or recording a take-on upload. Each line carries a
     * `is_group` flag so callers can skip the /000 group-header rows.
     *
     * @param mixed $file
     * @return array{0:int,1:array}
     * @throws Exception
     */
    public function parseFile(mixed $file): array
    {
        [$year, $lines] = $this->extractLines($file);

        return [$year, $lines];
    }

    /**
     * Read the budget sheet, derive the year, and pull each account line
     * (code, name, category, 12 months, per-year). Returns [year, lines, errors].
     *
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    private function extractLines(mixed $file): array
    {
        if (!$file) {
            throw new Exception('No file provided');
        }

        $rows = $this->readRows($file);
        $year = $this->extractYear($rows);

        $lines           = [];
        $errors          = [];
        $currentCategory = null;

        foreach ($rows as $idx => $row) {
            $label = trim((string) ($row[0] ?? ''));
            if ($label === '' || !preg_match('/^(\d+\/\d+|RF[IE]\/\d+)\s*-\s*(.+)$/', $label, $m)) {
                continue;
            }

            $code = $m[1];
            $name = trim($m[2]);

            // A "/000" row is a section header — its name becomes the category for
            // the accounts that follow (and for itself).
            if (str_ends_with($code, '/000')) {
                $currentCategory = $name;
            }

            $months = [];
            foreach (self::MONTHS as $i => $key) {
                $months[$key] = $this->decimal($row[$i + 1] ?? '');
            }
            $perYear = $this->decimal($row[13] ?? '');
            // Fall back to the sum of months when the "Per Year" column is blank.
            if ($perYear === 0.0) {
                $perYear = array_sum($months);
            }

            $lines[] = [
                'code'      => $code,
                'name'      => $name,
                'category'  => $currentCategory ?? ($code[0] === '1' ? 'INCOME' : 'EXPENSES'),
                'months'    => $months,
                'per_year'  => $perYear,
                'is_group'  => str_ends_with($code, '/000'),
                'row'       => $idx + 1,
            ];
        }

        if (empty($lines)) {
            throw new Exception('No budget account lines were found. Please use the WeConnectU budget template.');
        }

        return [$year, $lines, $errors];
    }

    /**
     * Read the first non-summary worksheet as a positional array of rows.
     *
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    private function readRows(mixed $file): array
    {
        $path   = $file->getRealPath();
        $reader = IOFactory::createReaderForFile($path);

        // The budget's header rows contain spaces, which trips PhpSpreadsheet's CSV
        // delimiter auto-detection — pin comma so a .csv export parses like the xlsx.
        if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
        }

        $spreadsheet = $reader->load($path);

        $sheet = null;
        for ($i = 0; $i < $spreadsheet->getSheetCount(); $i++) {
            $candidate = $spreadsheet->getSheet($i);
            if (!preg_match('/summary/i', $candidate->getTitle())) {
                $sheet = $candidate;
                break;
            }
        }
        $sheet = $sheet ?? $spreadsheet->getSheet(0);

        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            throw new Exception('The uploaded file is empty.');
        }

        return $rows;
    }

    /**
     * Derive the budget year from the title row's date range, defaulting to now.
     *
     * @param array $rows
     * @return int
     */
    private function extractYear(array $rows): int
    {
        foreach (array_slice($rows, 0, 4) as $row) {
            foreach ($row as $cell) {
                if (preg_match('/\b(20\d{2})\b/', (string) $cell, $m)) {
                    return (int) $m[1];
                }
            }
        }

        return (int) date('Y');
    }

    /**
     * Parse a numeric cell into a float, tolerating currency/thousands formatting.
     *
     * @param mixed $value
     * @return float
     */
    private function decimal(mixed $value): float
    {
        $value = trim(str_replace([',', ' ', 'R'], '', (string) $value));

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
