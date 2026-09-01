<?php

namespace App\Services;

use Exception;
use App\Models\Community;
use App\Models\CustomerGroup;
use App\Models\Owner;
use App\Enums\MandateType;
use App\Helpers\BankHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\OwnerResources;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerService extends BaseService
{
    /**
     * A customer IS an owner record — reuse the owner resource classes.
     *
     * @var class-string
     */
    protected string $resourceClass = OwnerResource::class;

    /**
     * @var class-string
     */
    protected string $resourceCollectionClass = OwnerResources::class;

    /**
     * Header labels for the "Upload Customer Notes" template.
     */
    private const NOTES_COLUMNS = ['Customer', 'Notes'];

    /**
     * Header labels for the "Update Debit Order Mandates" template (exact WeConnectU wording).
     */
    private const MANDATE_COLUMNS = [
        'BANK NAME',
        'BRANCH CODE',
        'ACCOUNT NAME',
        'ACCOUNT NUMBER',
        'CUSTOMER CODE',
        'ACCOUNT TYPE (CURRENT / SAVINGS)',
        'MANDATE TYPE (Balance / Monthly / Monthly Plus)',
        'Monthly Plus Amount (if any)',
        'Collection Date group (1 / 15 /28)',
    ];

    /**
     * Return a paginated, filtered list of customers for a community.
     *
     * @param Community $community
     * @param array $data
     * @return OwnerResources|array
     */
    public function showCustomers(Community $community, array $data): OwnerResources|array
    {
        $user  = Auth::user();
        $query = Owner::where('organization_id', $user->organization_id)
            ->forCommunity($community->id)
            ->with(['unit', 'customerGroups']);

        if (array_key_exists('is_disabled', $data) && $data['is_disabled'] !== null && $data['is_disabled'] !== '') {
            $query->where('is_disabled', filter_var($data['is_disabled'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!request()->has('_sort')) {
            $query = $query->orderBy('customer_code')->orderBy('full_name');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a customer (owner). Auto-generates the customer code when blank and
     * syncs the selected customer groups.
     *
     * @param Community $community
     * @param array $data
     * @return array
     */
    public function createCustomer(Community $community, array $data): array
    {
        $user     = Auth::user();
        $groupIds = $data['customer_group_ids'] ?? [];

        $attrs = collect($data)
            ->except(['customer_group_ids'])
            ->toArray();

        $attrs['organization_id'] = $user->organization_id;
        $attrs['community_id']    = $community->id;
        $attrs['payment_type']    = $attrs['payment_type'] ?? 'not_specified';

        $owner = Owner::create($attrs);

        if (empty($owner->customer_code)) {
            $owner->update(['customer_code' => $this->generateCustomerCode($community, $owner)]);
        }

        $this->syncGroups($community, $owner, $groupIds);

        $owner->load(['unit', 'customerGroups']);

        return $this->showCreatedResource($owner);
    }

    /**
     * Return a single customer with its relationships loaded.
     *
     * @param Owner $owner
     * @return OwnerResource
     */
    public function showCustomer(Owner $owner): OwnerResource
    {
        $owner->load(['unit.community', 'customerGroups', 'invoices.ledger']);

        return $this->showResource($owner);
    }

    /**
     * Update a customer and re-sync its customer groups.
     *
     * @param Community $community
     * @param Owner $owner
     * @param array $data
     * @return array
     */
    public function updateCustomer(Community $community, Owner $owner, array $data): array
    {
        $groupIds = $data['customer_group_ids'] ?? null;

        $owner->update(
            collect($data)->except(['customer_group_ids'])->toArray()
        );

        if (is_array($groupIds)) {
            $this->syncGroups($community, $owner, $groupIds);
        }

        $owner->load(['unit', 'customerGroups']);

        return $this->showUpdatedResource($owner);
    }

    /**
     * Disable a customer (WeConnectU "Disable Customer").
     *
     * @param Owner $owner
     * @return array
     */
    public function disableCustomer(Owner $owner): array
    {
        $alreadyDisabled = (bool) $owner->is_disabled;

        if (!$alreadyDisabled) {
            $owner->update(['is_disabled' => true, 'disabled_at' => now()]);
        }

        return [
            'disabled' => true,
            'message'  => $alreadyDisabled ? 'Customer already disabled' : 'Customer disabled',
        ];
    }

    /**
     * Re-enable a disabled customer.
     *
     * @param Owner $owner
     * @return array
     */
    public function enableCustomer(Owner $owner): array
    {
        $owner->update(['is_disabled' => false, 'disabled_at' => null]);

        return [
            'enabled' => true,
            'message' => 'Customer enabled',
        ];
    }

    /**
     * Delete a customer.
     *
     * @param Owner $owner
     * @return array
     */
    public function deleteCustomer(Owner $owner): array
    {
        $deleted = $owner->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Customer deleted' : 'Customer delete unsuccessful',
        ];
    }

    /**
     * Stream the "Upload Customer Notes" template (Customer code + Notes).
     *
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadNotesTemplate(Community $community): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        $col = 'A';
        foreach (self::NOTES_COLUMNS as $header) {
            $cell = $col . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('33CCCC');
            $col++;
        }

        // Seed the existing customer codes so managers can fill notes against them.
        $codes = $this->customerCodes($community);
        $row   = 2;
        foreach ($codes as $code) {
            $sheet->setCellValue('A' . $row, $code);
            $row++;
        }

        return $this->streamXlsx($spreadsheet, 'upload-customer-notes-template.xlsx');
    }

    /**
     * Import customer notes from an uploaded sheet, matching by customer code.
     *
     * @param Community $community
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    public function importNotes(Community $community, mixed $file): array
    {
        $rows      = $this->readRows($file);
        $headerIdx = $this->findHeaderRow($rows, self::NOTES_COLUMNS, 2);
        if ($headerIdx === null) {
            throw new Exception('Could not find the template header row. Please use the supplied Customer Notes template.');
        }

        $map      = $this->buildColumnMap($rows[$headerIdx], ['customer' => 'code', 'notes' => 'notes']);
        $dataRows = array_slice($rows, $headerIdx + 1);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($community, $dataRows, $map, $headerIdx, &$imported, &$skipped, &$errors) {
            foreach ($dataRows as $offset => $raw) {
                $rowNum = $headerIdx + $offset + 2;
                $code   = trim((string) ($raw[$map['code']] ?? ''));
                $notes  = trim((string) ($raw[$map['notes']] ?? ''));

                if ($code === '' && $notes === '') {
                    continue;
                }
                if ($code === '') {
                    $skipped++;
                    $errors[] = ['row' => $rowNum, 'errors' => ['Customer code is missing.']];
                    continue;
                }

                $customer = $this->matchCustomer($community, $code);
                if (!$customer) {
                    $skipped++;
                    $errors[] = ['row' => $rowNum, 'errors' => ["No customer found for code '{$code}'."]];
                    continue;
                }

                $customer->update(['notes' => $notes !== '' ? $notes : null]);
                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => "{$imported} customer notes updated" . ($skipped ? ", {$skipped} skipped." : '.'),
        ];
    }

    /**
     * Stream the "Update Debit Order Mandates" template with validation dropdowns.
     *
     * @param Community $community
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadMandatesTemplate(Community $community): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        $col = 'A';
        foreach (self::MANDATE_COLUMNS as $header) {
            $cell = $col . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('33CCCC');
            $col++;
        }

        // Data-validation dropdowns (mirror the WeConnectU template).
        $this->applyDropdown($sheet, 'A', '"' . implode(',', BankHelper::banks()) . '"');            // BANK NAME
        $this->applyDropdown($sheet, 'F', '"CURRENT,SAVINGS"');                                        // ACCOUNT TYPE
        $this->applyDropdown($sheet, 'G', '"Balance,Monthly,Monthly Plus"');                           // MANDATE TYPE
        $this->applyDropdown($sheet, 'I', '"1,15,28"');                                                // COLLECTION DATE GROUP

        return $this->streamXlsx($spreadsheet, 'update-debit-order-mandates-template.xlsx');
    }

    /**
     * Import debit-order mandates from an uploaded sheet, matching by customer code.
     *
     * @param Community $community
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    public function importMandates(Community $community, mixed $file): array
    {
        $rows      = $this->readRows($file);
        $headerIdx = $this->findHeaderRow($rows, self::MANDATE_COLUMNS, 4);
        if ($headerIdx === null) {
            throw new Exception('Could not find the template header row. Please use the supplied Debit Order Mandates template.');
        }

        $map = $this->buildColumnMap($rows[$headerIdx], [
            'bank name'                                       => 'bank_name',
            'branch code'                                     => 'branch_code',
            'account name'                                    => 'account_holder',
            'account number'                                  => 'account_number',
            'customer code'                                   => 'code',
            'account type (current / savings)'                => 'account_type',
            'mandate type (balance / monthly / monthly plus)' => 'mandate_type',
            'monthly plus amount (if any)'                    => 'monthly_plus_amount',
            'collection date group (1 / 15 /28)'              => 'collection_day',
        ]);
        $dataRows = array_slice($rows, $headerIdx + 1);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::transaction(function () use ($community, $dataRows, $map, $headerIdx, &$imported, &$skipped, &$errors) {
            foreach ($dataRows as $offset => $raw) {
                $rowNum = $headerIdx + $offset + 2;
                $code   = trim((string) ($raw[$map['code'] ?? -1] ?? ''));

                if ($code === '') {
                    continue;
                }

                $customer = $this->matchCustomer($community, $code);
                if (!$customer) {
                    $skipped++;
                    $errors[] = ['row' => $rowNum, 'errors' => ["No customer found for code '{$code}'."]];
                    continue;
                }

                $accountType = $this->normalizeAccountType((string) ($raw[$map['account_type'] ?? -1] ?? ''));
                $mandate     = MandateType::fromLabel((string) ($raw[$map['mandate_type'] ?? -1] ?? ''));
                $collection  = $this->normalizeCollectionDay((string) ($raw[$map['collection_day'] ?? -1] ?? ''));

                $customer->update(array_filter([
                    'bank_name'           => $this->cell($raw, $map, 'bank_name'),
                    'branch_code'         => $this->cell($raw, $map, 'branch_code'),
                    'account_holder'      => $this->cell($raw, $map, 'account_holder'),
                    'account_number'      => $this->cell($raw, $map, 'account_number'),
                    'account_type'        => $accountType,
                    'mandate_type'        => $mandate?->value,
                    'monthly_plus_amount' => $this->decimal((string) ($raw[$map['monthly_plus_amount'] ?? -1] ?? '')),
                    'collection_day'      => $collection,
                    'debit_order'         => true,
                    'payment_type'        => 'debit_order',
                ], fn ($v) => $v !== null && $v !== ''));

                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => "{$imported} debit-order mandates updated" . ($skipped ? ", {$skipped} skipped." : '.'),
        ];
    }

    /**
     * Sync a customer's groups, restricting to groups within the community.
     *
     * @param Community $community
     * @param Owner $owner
     * @param array $groupIds
     * @return void
     */
    private function syncGroups(Community $community, Owner $owner, array $groupIds): void
    {
        $validIds = CustomerGroup::where('community_id', $community->id)
            ->whereIn('id', $groupIds)
            ->pluck('id')
            ->all();

        $owner->customerGroups()->sync($validIds);
    }

    /**
     * Generate a unique customer code: 3-letter name prefix + community sequence,
     * with a -U{unit} suffix when the customer is linked to a unit.
     *
     * @param Community $community
     * @param Owner $owner
     * @return string
     */
    private function generateCustomerCode(Community $community, Owner $owner): string
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $owner->full_name));
        $prefix  = $letters !== '' ? str_pad(substr($letters, 0, 3), 3, 'X') : 'CUS';

        $unitNumber = $owner->unit?->unit_number;
        $suffix     = $unitNumber ? '-U' . $unitNumber : '';

        $base = Owner::where('organization_id', $owner->organization_id)
            ->forCommunity($community->id)
            ->where('id', '!=', $owner->id);

        $seq = (clone $base)->where('customer_code', 'like', $prefix . '%')->count() + 1;

        do {
            $code   = sprintf('%s%03d%s', $prefix, $seq, $suffix);
            $exists = (clone $base)->where('customer_code', $code)->exists();
            $seq++;
        } while ($exists);

        return $code;
    }

    /**
     * Match a customer within a community by its code (owner code or unit code).
     *
     * @param Community $community
     * @param string $code
     * @return Owner|null
     */
    private function matchCustomer(Community $community, string $code): ?Owner
    {
        $user = Auth::user();

        return Owner::where('organization_id', $user->organization_id)
            ->forCommunity($community->id)
            ->where(function ($query) use ($code) {
                $query->where('customer_code', $code)
                      ->orWhereHas('unit', fn ($unit) => $unit->where('customer_code', $code));
            })
            ->first();
    }

    /**
     * Existing customer codes for the community (owner code, else unit code).
     *
     * @param Community $community
     * @return array<int, string>
     */
    private function customerCodes(Community $community): array
    {
        $user = Auth::user();

        return Owner::where('organization_id', $user->organization_id)
            ->forCommunity($community->id)
            ->with('unit:id,customer_code')
            ->orderBy('customer_code')
            ->get()
            ->map(fn (Owner $owner) => $owner->customer_code ?: $owner->unit?->customer_code)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Read every row of the uploaded spreadsheet as a positional array.
     *
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    private function readRows(mixed $file): array
    {
        if (!$file) {
            throw new Exception('No file provided');
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows        = $spreadsheet->getSheet(0)->toArray(null, true, true, false);

        if (empty($rows)) {
            throw new Exception('The uploaded file is empty.');
        }

        return $rows;
    }

    /**
     * Locate the header row by matching known column labels.
     *
     * @param array $rows
     * @param array $headers
     * @param int $minHits
     * @return int|null
     */
    private function findHeaderRow(array $rows, array $headers, int $minHits): ?int
    {
        $known = array_map(fn ($h) => $this->normalize($h), $headers);

        foreach ($rows as $idx => $row) {
            $hits = 0;
            foreach ($row as $cell) {
                if (in_array($this->normalize((string) $cell), $known, true)) {
                    $hits++;
                }
            }
            if ($hits >= $minHits) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Map spreadsheet column indexes to system keys from a normalized header lookup.
     *
     * @param array $headerRow
     * @param array $lookup  normalized header => system key
     * @return array  [systemKey => columnIndex]
     */
    private function buildColumnMap(array $headerRow, array $lookup): array
    {
        $map = [];
        foreach ($headerRow as $idx => $cell) {
            $norm = $this->normalize((string) $cell);
            if (isset($lookup[$norm])) {
                $map[$lookup[$norm]] = $idx;
            }
        }

        return $map;
    }

    /**
     * Read a trimmed cell value by system key, or null when blank/missing.
     *
     * @param array $raw
     * @param array $map
     * @param string $key
     * @return string|null
     */
    private function cell(array $raw, array $map, string $key): ?string
    {
        if (!isset($map[$key])) {
            return null;
        }
        $value = trim((string) ($raw[$map[$key]] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Add a list data-validation dropdown down a column (rows 2–500).
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param string $column
     * @param string $formula1
     * @return void
     */
    private function applyDropdown($sheet, string $column, string $formula1): void
    {
        for ($row = 2; $row <= 500; $row++) {
            $validation = $sheet->getCell($column . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($formula1);
        }
    }

    /**
     * Normalize an account type value (CURRENT/SAVINGS/Investment).
     *
     * @param string $value
     * @return string|null
     */
    private function normalizeAccountType(string $value): ?string
    {
        $value = trim($value);

        return match (mb_strtolower($value)) {
            'current'    => 'Current',
            'savings'    => 'Savings',
            'investment' => 'Investment',
            default      => $value === '' ? null : $value,
        };
    }

    /**
     * Normalize a collection-day value to one of 1/15/28.
     *
     * @param string $value
     * @return int|null
     */
    private function normalizeCollectionDay(string $value): ?int
    {
        $value = (int) preg_replace('/[^0-9]/', '', $value);

        return in_array($value, [1, 15, 28], true) ? $value : null;
    }

    /**
     * Normalize a header/label for tolerant matching (lowercase, collapse spaces).
     *
     * @param string $value
     * @return string
     */
    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }

    /**
     * Parse a numeric cell into a float, or null when blank/non-numeric.
     *
     * @param string $value
     * @return float|null
     */
    private function decimal(string $value): ?float
    {
        $value = trim(str_replace([',', ' '], ['', ''], $value));

        return ($value === '' || !is_numeric($value)) ? null : (float) $value;
    }

    /**
     * Stream a spreadsheet as an xlsx download response.
     *
     * @param Spreadsheet $spreadsheet
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
