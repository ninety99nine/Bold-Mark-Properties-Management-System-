<?php

namespace App\Services;

use Exception;
use App\Enums\LedgerAppliesTo;
use App\Models\Community;
use App\Models\CommunityBudget;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BudgetImportService extends BaseService
{
    /**
     * Ordered month keys matching the 12 columns (B–M) of the WeConnectU budget.
     */
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    /**
     * The exact WeConnectU budget income accounts (in file order), "code - name".
     */
    private const INCOME_LINES = [
        '1000/000 - INCOME',
        '1000/001 - Levies',
        '1000/002 - Special Levy',
        '1000/003 - Interest Received Arrears',
        '1000/004 - Interest Received Bank',
        '1000/005 - Rental Income',
        '1000/006 - Laundry Room Water',
        '1000/007 - Additional Levy',
        '1000/008 - Additional insurance',
        '1000/009 - Remotes Income',
        '1000/010 - Penalty Income',
        '1000/011 - Water Recovered',
        '1000/012 - Sewerage Recovered',
        '1000/013 - Electricity Recovered',
        '1000/014 - Other Income',
        '1000/015 - Costs Recovered',
    ];

    /**
     * The exact WeConnectU budget expense accounts (in file order), "code - name".
     */
    private const EXPENSE_LINES = [
        '2000/000 - ADMINISTRATIVE EXPENSES',
        '2000/001 - Bank Charges',
        '2000/002 - Management Fee',
        '2000/003 - Cleaning & Materials',
        '2000/004 - Computer Expenses',
        '2000/005 - General Expenses',
        '2000/006 - Keys & Remotes',
        '2000/007 - Legal & Professional Fees',
        '2000/008 - Telephone: Mobile(s)',
        '2000/009 - Telephone: Landline(s)',
        '2000/010 - Telephone: Gate access',
        '2000/011 - Audit & Tax Fees',
        '2000/012 - Trustee Expense',
        '2000/013 - Rent Paid: Garage',
        '2000/014 - Rent Paid: Office Space',
        '2000/015 - Security',
        '2000/016 - Insurance',
        '2000/017 - Interest Paid',
        '2000/018 - Health & Safety',
        '2000/019 - Income Tax Expense',
        '2000/020 - Depreciation',
        '2000/021 - Diverse/Sundry Expenses',
        '2000/022 - CSOS Admin Fees',
        '2100/000 - MUNICIPAL EXPENSES',
        '2100/001 - Water',
        '2100/002 - Refuse',
        '2100/003 - Sewerage',
        '2100/004 - Electricity',
        '2100/005 - Rates',
        '2100/006 - Sundry Municipal Expenses',
        '2100/007 - Fixed Basic Charge - Water',
        '2100/008 - Electricity - Home User Charge',
        '2200/000 - MAINTENANCE',
        '2200/001 - Fire Equipment & Services',
        '2200/002 - General Building',
        '2200/003 - Sewerage & Plumbing',
        '2200/004 - Gate & Intercom',
        '2200/005 - Electrical',
        '2200/006 - Electric Fence & Monitoring',
        '2200/007 - DSTV / TV',
        '2200/008 - Turnstile & Access Control',
        '2200/009 - Gardening Expense General',
        '2200/010 - Gardening Equipment',
        '2200/011 - Cameras',
        '2200/012 - Swimming Pool',
        '2200/013 - Lifts',
        '2200/014 - Cleaning and Maintenance Contracts',
        '2200/015 - Other Maintenance',
        '2200/016 - Pest Control',
        '2300/000 - SPECIAL PROJECTS',
        '2300/001 - Buildings',
        '2300/002 - Gardens',
        '2300/003 - Improvements',
        '2300/004 - Valuations - 3 Year Cycle',
        '4000/000 - PERSONNEL',
        '4000/001 - Complex Manager Salary',
        '4000/002 - Operational Admin',
        '4000/003 - Casual / Relief Wages',
        '4000/004 - PAYE',
        '4000/005 - UIF',
        '4000/006 - Travel',
        '4000/007 - Bonusses & Overtime',
        '4000/008 - Security',
        '4000/009 - WCA',
    ];

    /**
     * Stream the WeConnectU-format budget template, pre-populated with the exact
     * standard chart of accounts and a zeroed grid (a byte-for-byte-faithful
     * replica of WeConnectU's downloadable budget).
     *
     * @param Community $community
     * @param string $format  'xlsx'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadTemplate(Community $community, string $format = 'xlsx'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $year = (int) date('Y');

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Worksheet');

        // Rows 1–2: title + date range, then community name.
        $sheet->setCellValue('A1', 'Actual Budget');
        $sheet->setCellValue('B1', "{$year}-01-01 to {$year}-12-31");
        $sheet->setCellValue('A2', $community->name);

        // Row 4: month headers (B–M) + Per Year (N). Row 5: the year under each month.
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $col = 'B';
        foreach ($months as $m) {
            $sheet->setCellValue($col . '4', $m);
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

        // Row 6: TOTAL INCOME (label only) → income accounts (each with 13 zeros).
        $rowNum = 6;
        $sheet->setCellValue('A' . $rowNum, 'TOTAL INCOME');
        $rowNum++;
        $rowNum = $this->writeChartLines($sheet, self::INCOME_LINES, $rowNum);

        // Blank row, then TOTAL EXPENSES (label only) → expense accounts.
        $rowNum++;
        $sheet->setCellValue('A' . $rowNum, 'TOTAL EXPENSES');
        $rowNum++;
        $this->writeChartLines($sheet, self::EXPENSE_LINES, $rowNum);

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'budget-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Write account rows (label in A, twelve months + per-year zeros in B–N),
     * returning the next free row number.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param array $lines
     * @param int $rowNum
     * @return int
     */
    private function writeChartLines($sheet, array $lines, int $rowNum): int
    {
        foreach ($lines as $label) {
            $sheet->setCellValue('A' . $rowNum, $label);
            foreach (range('B', 'N') as $c) {
                $sheet->setCellValue($c . $rowNum, 0);
            }
            $sheet->getStyle('B' . $rowNum . ':N' . $rowNum)->getAlignment()->setHorizontal('right');
            $rowNum++;
        }

        return $rowNum;
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
            if ($label === '' || !preg_match('/^(\d+\/\d+)\s*-\s*(.+)$/', $label, $m)) {
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
            $perYear = 0.0;
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
        $spreadsheet = IOFactory::load($file->getRealPath());

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
