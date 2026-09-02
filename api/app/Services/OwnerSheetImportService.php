<?php

namespace App\Services;

use Exception;
use App\Models\Community;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class OwnerSheetImportService extends BaseService
{
    /**
     * The WeConnectU owner-sheet columns, in order, with their normalized header
     * text and the system field each maps to. Highlighted = compulsory in WeConnectU.
     */
    private const COLUMNS = [
        ['header' => 'Block',                     'key' => 'block_number',        'required' => false],
        ['header' => 'Section / Erf No',          'key' => 'section',             'required' => true],
        ['header' => 'Unit No',                   'key' => 'unit_number',         'required' => false],
        ['header' => 'Door No',                   'key' => 'door_number',         'required' => false],
        ['header' => 'Street No (HOA)',           'key' => 'street_no',           'required' => false],
        ['header' => 'PQ (If a Body Corporate)',  'key' => 'pq',                  'required' => true],
        ['header' => 'Size Unit (Sq m)',          'key' => 'unit_size',           'required' => false],
        ['header' => 'Size Garage (Sq m)',        'key' => 'garage_size',         'required' => false],
        ['header' => 'Size Carport (Sq m)',       'key' => 'carport_size',        'required' => false],
        ['header' => 'Size Parking (Sq m)',       'key' => 'parking_size',        'required' => false],
        ['header' => 'Ratio 1',                   'key' => 'ratio_1',             'required' => false],
        ['header' => 'Ratio 2',                   'key' => 'ratio_2',             'required' => false],
        ['header' => 'Ratio 3',                   'key' => 'ratio_3',             'required' => false],
        ['header' => 'Ratio 4',                   'key' => 'ratio_4',             'required' => false],
        ['header' => 'Ratio 5',                   'key' => 'ratio_5',             'required' => false],
        ['header' => 'Owner / Contact Name',      'key' => 'owner_full_name',     'required' => true],
        ['header' => 'ID / Passport',             'key' => 'owner_id_number',     'required' => false],
        ['header' => 'Email Address',             'key' => 'owner_email',         'required' => true],
        ['header' => 'Cell Number',               'key' => 'owner_phone',         'required' => false],
        ['header' => 'Landline Number',           'key' => 'owner_landline',      'required' => false],
        ['header' => 'Contact 2 Name',            'key' => 'owner_contact2_name',     'required' => false],
        ['header' => 'Contact 2 Email Address',   'key' => 'owner_contact2_email',    'required' => false],
        ['header' => 'Contact 2 Cell Number',     'key' => 'owner_contact2_phone',    'required' => false],
        ['header' => 'Contact 2 Landline Number', 'key' => 'owner_contact2_landline', 'required' => false],
        ['header' => 'Postal Address',            'key' => 'owner_address',       'required' => false],
        ['header' => 'Trust Name',                'key' => 'trust_name',          'required' => false],
        ['header' => 'Trust Reg',                 'key' => 'trust_reg',           'required' => false],
        ['header' => 'CC Name',                   'key' => 'cc_name',             'required' => false],
        ['header' => 'CC Reg No.',                'key' => 'cc_reg_no',           'required' => false],
        ['header' => 'PTY Name',                  'key' => 'pty_name',            'required' => false],
        ['header' => 'PTY Reg No.',               'key' => 'pty_reg_no',          'required' => false],
        ['header' => 'Body Corporate Name',       'key' => 'body_corporate_name',   'required' => false],
        ['header' => 'Body Corporate Reg No.',    'key' => 'body_corporate_reg_no', 'required' => false],
        ['header' => 'Rental Agent Email',        'key' => 'rental_agent_email',  'required' => false],
        ['header' => 'Customer Code',             'key' => 'customer_code',       'required' => false],
        ['header' => 'Customer Name',             'key' => 'customer_name',       'required' => false],
    ];

    /**
     * Stream the WeConnectU-format owner-sheet template for download.
     *
     * @param Community $community
     * @param string $format  'xlsx' (WeConnectU only ships Excel)
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadTemplate(Community $community, string $format = 'xlsx'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Exact WeConnectU column widths (36 columns, A–AJ).
        $widths = [10.29, 51.29, 7.57, 8.43, 15.71, 24.0, 15.29, 19.29, 19.14, 19.14, 7.29, 7.29, 7.29, 7.29, 7.29, 22.86, 12.57, 17.29, 12.71, 17.29, 16.14, 24.14, 22.57, 27.0, 14.71, 11.29, 9.29, 10.0, 11.71, 10.14, 11.86, 22.29, 24.0, 19.0, 15.43, 16.0];

        // WeConnectU guidance notes: row 6 per column, plus the row-7 multi-owner note.
        $hints6 = [
            2  => " - Section / Erf number is compulsory;\n - Also include details for the applicable \"Unit addressing\" method as selected (this will be used in generating a unique customer code) ",
            5  => 'Street name and number can be added here',
            6  => 'Must add up to 100',
            16 => "If the owner is a Trust / CC / Private Company / Body Corporate; please add the Contact person's details here, and add the owner details in the relevant column that follows.",
            18 => 'If no client info, insert your Company forwarding email address (to print communications and deliver via post)',
            35 => 'Please add customer codes per previous accounting system - for take-on reconciliation purposes only',
            36 => '**For Office use only',
        ];
        $hint7Col16 = 'Where more than one owner per Erf/ Section: FOR EACH ADDITIONAL OWNER: In a new row, repeat the Erf/Section number; Add additional owner details in this new separate row. (Therefore a separate line per individual owner)';

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        // WeConnectU uses Century Gothic throughout.
        $spreadsheet->getDefaultStyle()->getFont()->setName('Century Gothic')->setSize(10);

        $navy = 'FF002060';

        // Row 1: title — bold + underlined, 12pt.
        $sheet->setCellValue('A1', 'Set-up Owners / Customers');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setUnderline(true)->setSize(12);

        // Row 2: instruction — italic, navy, 11pt.
        $sheet->setCellValue('A2', 'Please prepare information on this sheet for your Current Owners / Customers - We will perform upload on your behalf');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11)->getColor()->setRGB($navy);

        // Row 3: a teal colour swatch (A3) + the "highlighted = compulsory" note (B3).
        $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('33CCCC');
        $sheet->setCellValue('B3', 'The highlighted columns indicates compulsory/required information');
        $sheet->getStyle('B3')->getFont()->setItalic(true)->setSize(11)->getColor()->setRGB($navy);

        // Header row (row 5) — bold; compulsory columns teal, the rest grey.
        $index = 1;
        foreach (self::COLUMNS as $c) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($letter)->setWidth($widths[$index - 1] ?? 15);
            $sheet->setCellValue($letter . '5', $c['header']);
            $sheet->getStyle($letter . '5')->getFont()->setBold(true);
            $sheet->getStyle($letter . '5')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($c['required'] ? '33CCCC' : 'D8D8D8');
            $index++;
        }

        // Guidance rows (6–7): italic navy notes under the relevant columns, wrapped.
        foreach ($hints6 as $colIndex => $text) {
            $cell = Coordinate::stringFromColumnIndex($colIndex) . '6';
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getFont()->setItalic(true)->getColor()->setRGB($navy);
            $sheet->getStyle($cell)->getAlignment()->setWrapText(true)->setVertical('top');
        }
        $p7 = Coordinate::stringFromColumnIndex(16) . '7';
        $sheet->setCellValue($p7, $hint7Col16);
        $sheet->getStyle($p7)->getFont()->setItalic(true)->setBold(true)->getColor()->setRGB($navy);
        $sheet->getStyle($p7)->getAlignment()->setWrapText(true)->setVertical('top');

        // Boxed table look (header + guidance rows), matching WeConnectU.
        $lastCol = Coordinate::stringFromColumnIndex(count(self::COLUMNS));
        $sheet->getStyle("A5:{$lastCol}7")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getRowDimension(6)->setRowHeight(120);
        $sheet->getRowDimension(7)->setRowHeight(90);

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'owner-sheet-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Parse an uploaded owner sheet into a normalized preview: grouped units +
     * owners, per-row errors, and a summary. Does not persist anything.
     *
     * @param Community $community
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    public function parse(Community $community, mixed $file): array
    {
        [$groups, $errors] = $this->extractGroups($file);

        $ownerCount = array_sum(array_map(fn ($g) => count($g['owners']), $groups));

        return [
            'columns' => array_map(fn ($c) => $c['header'], self::COLUMNS),
            'units'   => array_map(fn ($g) => [
                'unit'   => $g['unit'],
                'owners' => $g['owners'],
                'row'    => $g['row'],
            ], $groups),
            'errors'  => $errors,
            'summary' => [
                'units'       => count($groups),
                'owners'      => $ownerCount,
                'error_count' => count($errors),
            ],
        ];
    }

    /**
     * Re-parse the uploaded file and commit units + owners for the community.
     * Idempotent: units are matched by customer_code → unit_number → section, and
     * owners by email (or name) within the unit, so re-uploading updates in place.
     *
     * @param Community $community
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    public function import(Community $community, mixed $file): array
    {
        $user = Auth::user();
        [$groups, $errors] = $this->extractGroups($file);

        $importedUnits  = 0;
        $importedOwners = 0;

        DB::transaction(function () use ($groups, $community, $user, &$importedUnits, &$importedOwners) {
            foreach ($groups as $group) {
                $unitAttrs = $group['unit'];

                $unit = $this->matchUnit($community, $unitAttrs);
                if ($unit) {
                    $unit->update(array_filter($unitAttrs, fn ($v) => $v !== null && $v !== ''));
                } else {
                    $unit = Unit::create(array_merge($unitAttrs, [
                        'community_id'    => $community->id,
                        'organization_id' => $community->organization_id,
                        'status'          => 'active',
                    ]));
                }
                $importedUnits++;

                foreach ($group['owners'] as $index => $ownerAttrs) {
                    $ownerAttrs['is_primary'] = $index === 0;
                    $this->upsertOwner($unit, $ownerAttrs, $community->organization_id);
                    $importedOwners++;
                }
            }
        });

        // Store the raw file + mark the take-on "Owner Sheet" step as uploaded.
        (new CommunityTakeonService())->recordUpload($community, 'owner_sheet', $file);

        return [
            'imported_units'  => $importedUnits,
            'imported_owners' => $importedOwners,
            'error_count'     => count($errors),
            'errors'          => $errors,
            'message'         => "{$importedUnits} units and {$importedOwners} owners imported.",
        ];
    }

    /**
     * Read the spreadsheet, locate the WeConnectU header row, and group data rows
     * into units (with one-or-more owners each). Returns [groups, errors].
     *
     * @param mixed $file
     * @return array
     * @throws Exception
     */
    private function extractGroups(mixed $file): array
    {
        if (!$file) {
            throw new Exception('No file provided');
        }

        $rows       = $this->readRows($file);
        $headerIdx  = $this->findHeaderRow($rows);
        if ($headerIdx === null) {
            throw new Exception('Could not find the owner-sheet header row. Please use the WeConnectU customer template.');
        }

        $map      = $this->buildColumnMap($rows[$headerIdx]);
        $dataRows = array_slice($rows, $headerIdx + 1);

        $groups     = [];
        $errors     = [];
        $currentKey = null;

        foreach ($dataRows as $offset => $raw) {
            $rowNum = $headerIdx + $offset + 2; // 1-based sheet row number

            $row = $this->mapRow($raw, $map);

            $ownerName  = trim($row['owner_full_name'] ?? '');
            $ownerEmail = trim($row['owner_email'] ?? '');
            $section    = trim($row['section'] ?? '');
            $unitNo     = trim($row['unit_number'] ?? '');
            $block      = trim($row['block_number'] ?? '');

            // Skip WeConnectU's instruction/hint rows: they carry long sentences in
            // the compulsory columns rather than short identifiers, names, or emails.
            $isHint = false;
            foreach (['section', 'unit_number', 'pq', 'owner_full_name', 'owner_email'] as $k) {
                if (mb_strlen(trim($row[$k] ?? '')) > 60) {
                    $isHint = true;
                    break;
                }
            }
            if ($isHint) {
                continue;
            }

            // Skip blank rows: a real row needs an identifier plus owner/PQ detail.
            $hasIdentifier = $section !== '' || $unitNo !== '' || $block !== '';
            $hasData       = $ownerName !== '' || $ownerEmail !== '' || is_numeric($this->decimal($row['pq'] ?? ''));
            if (!$hasIdentifier || !$hasData) {
                continue;
            }

            $key = $this->unitKey($row);
            $rowErrors = [];

            // A row that identifies a new unit starts a new group.
            if ($key !== '' && $key !== $currentKey) {
                foreach (self::COLUMNS as $c) {
                    if ($c['required'] && trim($row[$c['key']] ?? '') === '') {
                        $rowErrors[] = "'{$c['header']}' is required.";
                    }
                }
                if ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = "Email '{$ownerEmail}' is not a valid email address.";
                }

                if ($rowErrors) {
                    $errors[] = ['row' => $rowNum, 'errors' => $rowErrors];
                    continue;
                }

                $groups[]   = ['unit' => $this->unitAttrs($row), 'owners' => [], 'row' => $rowNum];
                $currentKey = $key;
            } elseif ($currentKey === null) {
                // First meaningful row carries no unit key — still create a unit for it.
                $groups[]   = ['unit' => $this->unitAttrs($row), 'owners' => [], 'row' => $rowNum];
                $currentKey = $key;
            }

            // Attach the owner on this row (primary for the first, additional after).
            if ($ownerName !== '' || $ownerEmail !== '') {
                if ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['row' => $rowNum, 'errors' => ["Email '{$ownerEmail}' is not a valid email address."]];
                    continue;
                }
                $groups[count($groups) - 1]['owners'][] = $this->ownerAttrs($row);
            }
        }

        return [$groups, $errors];
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
        $spreadsheet = IOFactory::load($file->getRealPath());

        // Prefer the first non-"summary" sheet (Numbers/Mac exports add one).
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
     * Locate the header row by matching known WeConnectU column labels.
     *
     * @param array $rows
     * @return int|null
     */
    private function findHeaderRow(array $rows): ?int
    {
        $known = array_map(fn ($c) => $this->normalize($c['header']), self::COLUMNS);
        $known[] = 'pq'; // tolerate a short "PQ" header too

        foreach ($rows as $idx => $row) {
            $hits = 0;
            foreach ($row as $cell) {
                if (in_array($this->normalize((string) $cell), $known, true)) {
                    $hits++;
                }
            }
            if ($hits >= 5) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Map each spreadsheet column index to a system field key using header text.
     *
     * @param array $headerRow
     * @return array  [columnIndex => systemKey]
     */
    private function buildColumnMap(array $headerRow): array
    {
        $lookup = [];
        foreach (self::COLUMNS as $c) {
            $lookup[$this->normalize($c['header'])] = $c['key'];
        }
        $lookup['pq'] = 'pq'; // tolerate a short "PQ" header too

        $map = [];
        foreach ($headerRow as $idx => $cell) {
            $norm = $this->normalize((string) $cell);
            if (isset($lookup[$norm])) {
                $map[$idx] = $lookup[$norm];
            }
        }

        return $map;
    }

    /**
     * Turn a positional data row into a system-keyed associative row.
     *
     * @param array $raw
     * @param array $map
     * @return array
     */
    private function mapRow(array $raw, array $map): array
    {
        $row = [];
        foreach ($map as $idx => $key) {
            $row[$key] = isset($raw[$idx]) ? trim((string) $raw[$idx]) : '';
        }

        return $row;
    }

    /**
     * Build a stable key identifying the unit a row belongs to. WeConnectU repeats
     * the Erf/Section (not necessarily the Unit No) on each additional-owner row, so
     * the section is the primary identifier, with Unit No as a fallback.
     *
     * @param array $row
     * @return string
     */
    private function unitKey(array $row): string
    {
        $block   = strtolower(trim($row['block_number'] ?? ''));
        $section = strtolower(trim($row['section'] ?? ''));
        $unitNo  = strtolower(trim($row['unit_number'] ?? ''));

        $identifier = $section !== '' ? $section : $unitNo;

        return $identifier === '' ? '' : $block . '|' . $identifier;
    }

    /**
     * Extract unit attributes from a mapped row.
     *
     * @param array $row
     * @return array
     */
    private function unitAttrs(array $row): array
    {
        // WeConnectU HOAs may only supply an Erf/Section; fall back to it for the
        // unit number so every row yields an addressable unit.
        $unitNumber = trim($row['unit_number'] ?? '') ?: trim($row['section'] ?? '');

        return [
            'unit_number'        => $unitNumber ?: null,
            'block_number'       => $this->nullable($row['block_number'] ?? ''),
            'section'            => $this->nullable($row['section'] ?? ''),
            'door_number'        => $this->nullable($row['door_number'] ?? ''),
            'address'            => $this->nullable($row['street_no'] ?? ''),
            'pq'                 => $this->decimal($row['pq'] ?? ''),
            'unit_size'          => $this->decimal($row['unit_size'] ?? ''),
            'garage_size'        => $this->decimal($row['garage_size'] ?? ''),
            'carport_size'       => $this->decimal($row['carport_size'] ?? ''),
            'parking_size'       => $this->decimal($row['parking_size'] ?? ''),
            'ratio_1'            => $this->decimal($row['ratio_1'] ?? ''),
            'ratio_2'            => $this->decimal($row['ratio_2'] ?? ''),
            'ratio_3'            => $this->decimal($row['ratio_3'] ?? ''),
            'ratio_4'            => $this->decimal($row['ratio_4'] ?? ''),
            'ratio_5'            => $this->decimal($row['ratio_5'] ?? ''),
            'rental_agent_email' => $this->nullable($row['rental_agent_email'] ?? ''),
            'customer_code'      => $this->nullable($row['customer_code'] ?? ''),
        ];
    }

    /**
     * Extract owner attributes from a mapped row.
     *
     * @param array $row
     * @return array
     */
    private function ownerAttrs(array $row): array
    {
        // WeConnectU supports comma/semicolon-separated emails: first is primary.
        $emailParts = array_values(array_filter(array_map('trim', preg_split('/[;,]/', $row['owner_email'] ?? ''))));

        $fullName = trim($row['owner_full_name'] ?? '') ?: trim($row['customer_name'] ?? '');

        return [
            'full_name'             => $fullName ?: 'Unknown Owner',
            'email'                 => $emailParts[0] ?? null,
            'secondary_emails'      => count($emailParts) > 1 ? array_slice($emailParts, 1) : null,
            'phone'                 => $this->nullable($row['owner_phone'] ?? ''),
            'landline'              => $this->nullable($row['owner_landline'] ?? ''),
            'id_number'             => $this->nullable($row['owner_id_number'] ?? ''),
            'contact2_name'         => $this->nullable($row['owner_contact2_name'] ?? ''),
            'contact2_email'        => $this->nullable($row['owner_contact2_email'] ?? ''),
            'contact2_phone'        => $this->nullable($row['owner_contact2_phone'] ?? ''),
            'contact2_landline'     => $this->nullable($row['owner_contact2_landline'] ?? ''),
            'address'               => $this->nullable($row['owner_address'] ?? ''),
            'trust_name'            => $this->nullable($row['trust_name'] ?? ''),
            'trust_reg'             => $this->nullable($row['trust_reg'] ?? ''),
            'cc_name'               => $this->nullable($row['cc_name'] ?? ''),
            'cc_reg_no'             => $this->nullable($row['cc_reg_no'] ?? ''),
            'pty_name'              => $this->nullable($row['pty_name'] ?? ''),
            'pty_reg_no'            => $this->nullable($row['pty_reg_no'] ?? ''),
            'body_corporate_name'   => $this->nullable($row['body_corporate_name'] ?? ''),
            'body_corporate_reg_no' => $this->nullable($row['body_corporate_reg_no'] ?? ''),
        ];
    }

    /**
     * Find an existing unit to update: customer_code → unit_number → section.
     *
     * @param Community $community
     * @param array $unitAttrs
     * @return Unit|null
     */
    private function matchUnit(Community $community, array $unitAttrs): ?Unit
    {
        $base = Unit::where('community_id', $community->id);

        if (!empty($unitAttrs['customer_code'])) {
            $hit = (clone $base)->where('customer_code', $unitAttrs['customer_code'])->first();
            if ($hit) {
                return $hit;
            }
        }
        if (!empty($unitAttrs['unit_number'])) {
            return (clone $base)->whereRaw('LOWER(unit_number) = ?', [strtolower($unitAttrs['unit_number'])])->first();
        }

        return null;
    }

    /**
     * Create or update an owner on a unit, matching by email (or name) for idempotency.
     *
     * @param Unit $unit
     * @param array $ownerAttrs
     * @param string $organizationId
     * @return void
     */
    private function upsertOwner(Unit $unit, array $ownerAttrs, string $organizationId): void
    {
        $query = Owner::where('unit_id', $unit->id);
        if (!empty($ownerAttrs['email'])) {
            $query->where('email', $ownerAttrs['email']);
        } else {
            $query->where('full_name', $ownerAttrs['full_name']);
        }
        $existing = $query->first();

        if ($existing) {
            $existing->update(array_filter($ownerAttrs, fn ($v) => $v !== null && $v !== ''));
            return;
        }

        Owner::create(array_merge($ownerAttrs, [
            'unit_id'         => $unit->id,
            'organization_id' => $organizationId,
        ]));
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
     * Return a trimmed string or null when empty.
     *
     * @param string $value
     * @return string|null
     */
    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
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
}
