<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Unit;
use App\Models\UnitActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitPqController extends Controller
{
    /**
     * Export a PQ spreadsheet for all units in an community.
     * Existing values are pre-filled so the manager only needs to update.
     * Supports ?format=csv or ?format=xlsx (default).
     */
    public function export(Request $request, Community $community)
    {
        $format = strtolower($request->query('format', 'xlsx'));

        $orderRaw = DB::getDriverName() === 'sqlite'
            ? 'unit_number asc'
            : "CASE WHEN REGEXP_REPLACE(unit_number, '[^0-9]', '') = '' THEN 1 ELSE 0 END, CAST(NULLIF(REGEXP_REPLACE(unit_number, '[^0-9]', ''), '') AS UNSIGNED), unit_number";

        $units = Unit::where('community_id', $community->id)
            ->orderByRaw($orderRaw)
            ->get(['unit_number', 'section', 'customer_code', 'pq', 'ratio_1', 'ratio_2', 'ratio_3', 'ratio_4', 'ratio_5', 'unit_size']);

        $count   = $units->count();
        $totalPq = (float) $units->sum('pq');

        // Ratio 1 = the unit's participation share as a percentage
        // (falls back to an equal share across all units when PQs are unset).
        $ratio1 = function ($unit) use ($totalPq, $count) {
            $pq = (float) ($unit->pq ?? 0);
            if ($totalPq > 0 && $pq > 0) {
                return round(($pq / $totalPq) * 100, 10);
            }
            return $count > 0 ? round(100 / $count, 10) : 0;
        };

        // WeConnectU-identical batch layout (columns + sheet name + filename).
        $headers = ['Section', 'Unit No', 'Customer Code', 'PQ', 'Ratio 1', 'Ratio 2', 'Ratio 3', 'Ratio 4', 'Ratio 5', 'Unit size(square metre)'];

        $rowFor = function ($unit) use ($ratio1) {
            return [
                ($unit->section !== null && $unit->section !== '') ? $unit->section : $unit->unit_number,
                $unit->unit_number,
                $unit->customer_code ?? '',
                (float) ($unit->pq ?? 0),
                $unit->ratio_1 !== null ? (float) $unit->ratio_1 : $ratio1($unit),
                (float) ($unit->ratio_2 ?? 0),
                (float) ($unit->ratio_3 ?? 0),
                (float) ($unit->ratio_4 ?? 0),
                (float) ($unit->ratio_5 ?? 0),
                (float) ($unit->unit_size ?? 0),
            ];
        };

        $slug = 'unit pqs-' . preg_replace('/\s+/', '', strtolower($community->name)) . '-' . now()->format('Y-m-d');

        if ($format === 'csv') {
            $rows = [$headers];
            foreach ($units as $unit) {
                $rows[] = $rowFor($unit);
            }
            $csv = '';
            foreach ($rows as $row) {
                $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row)) . "\r\n";
            }
            return response($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $slug . '.csv"',
            ]);
        }

        // XLSX — sheet name + columns identical to WeConnectU.
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Worksheet');

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}1", $header);
            $col++;
        }

        foreach ($units as $i => $unit) {
            $row = $i + 2;
            $col = 'A';
            foreach ($rowFor($unit) as $value) {
                $sheet->setCellValue("{$col}{$row}", $value);
                $col++;
            }
        }

        $filename = $slug . '.xlsx';
        $writer   = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Parse an uploaded PQ file and return columns + rows for client-side mapping.
     */
    public function parse(Request $request, Community $community)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $path = $request->file('file')->getRealPath();
        $ext  = strtolower($request->file('file')->getClientOriginalExtension());

        if ($ext === 'csv') {
            $rows = $this->parseCsv($path);
        } else {
            $spreadsheet = IOFactory::load($path);
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        }

        if (count($rows) < 1) {
            return response()->json(['message' => 'The file appears to be empty.'], 422);
        }

        // Extract header names from first row
        $firstRow = reset($rows);
        $columns  = array_values(array_filter(
            array_map(fn($v) => trim((string) $v), array_values($firstRow)),
            fn($c) => $c !== ''
        ));

        if (count($rows) < 2) {
            return response()->json(['columns' => $columns, 'rows' => [], 'total_rows' => 0]);
        }

        // Build data rows as column-name → value maps
        $dataRows = [];
        foreach (array_slice($rows, 1, null, true) as $row) {
            $values = array_values(array_map(fn($v) => trim((string) ($v ?? '')), $row));
            if (empty(array_filter($values, fn($v) => $v !== ''))) {
                continue; // skip blank rows
            }
            $mapped = [];
            foreach ($columns as $i => $col) {
                $mapped[$col] = $values[$i] ?? '';
            }
            $dataRows[] = $mapped;
        }

        return response()->json([
            'columns'    => $columns,
            'rows'       => $dataRows,
            'total_rows' => count($dataRows),
        ]);
    }

    /**
     * Import PQ values from an uploaded spreadsheet.
     * Matches rows by unit_number; updates section and pq atomically.
     */
    public function import(Request $request, Community $community)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $path = $request->file('file')->getRealPath();
        $ext  = strtolower($request->file('file')->getClientOriginalExtension());

        if ($ext === 'csv') {
            $rows = $this->parseCsv($path);
        } else {
            $spreadsheet = IOFactory::load($path);
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        }

        if (count($rows) < 2) {
            return response()->json(['message' => 'No data rows found in file.', 'updated' => 0], 422);
        }

        $firstRow = reset($rows);
        $headers  = array_map('strtolower', array_map('trim', array_map('strval', $firstRow)));
        $colMap   = array_flip($headers);

        // Accept both our legacy headers and WeConnectU's batch headers.
        $unitNumberCol   = $colMap['unit_number'] ?? $colMap['unit no'] ?? null;
        $customerCodeCol = $colMap['customer code'] ?? $colMap['customer_code'] ?? null;
        $pqCol           = $colMap['pq'] ?? null;
        $sectionCol      = $colMap['section'] ?? null;
        $levyOverrideCol = $colMap['levy_override'] ?? $colMap['levy override'] ?? null;

        // WeConnectU Ratio 1–5 + Unit Size columns.
        $ratioCols = [];
        for ($i = 1; $i <= 5; $i++) {
            $ratioCols[$i] = $colMap["ratio $i"] ?? $colMap["ratio_$i"] ?? $colMap["ratio{$i}"] ?? null;
        }
        $unitSizeCol = $colMap['unit size(square metre)'] ?? $colMap['unit_size'] ?? $colMap['unit size'] ?? null;

        if ($unitNumberCol === null && $customerCodeCol === null) {
            return response()->json([
                'message' => 'The file must contain a "Unit No" (or "unit_number") or "Customer Code" column.',
                'updated' => 0,
            ], 422);
        }

        $unitsByNumber = Unit::where('community_id', $community->id)
            ->pluck('id', 'unit_number')
            ->mapWithKeys(fn($id, $num) => [strtolower(trim((string) $num)) => $id]);

        $unitsByCode = Unit::where('community_id', $community->id)
            ->whereNotNull('customer_code')
            ->pluck('id', 'customer_code')
            ->mapWithKeys(fn($id, $code) => [strtolower(trim((string) $code)) => $id]);

        $updates  = [];
        $notFound = [];

        foreach (array_slice($rows, 1, null, true) as $row) {
            $ref    = '';
            $unitId = null;

            if ($unitNumberCol !== null) {
                $ref = trim(strval($row[$unitNumberCol] ?? ''));
                if ($ref !== '') {
                    $unitId = $unitsByNumber[strtolower($ref)] ?? null;
                }
            }
            if ($unitId === null && $customerCodeCol !== null) {
                $code = trim(strval($row[$customerCodeCol] ?? ''));
                if ($code !== '') {
                    $ref    = $ref !== '' ? $ref : $code;
                    $unitId = $unitsByCode[strtolower($code)] ?? null;
                }
            }

            if ($ref === '') {
                continue;
            }
            if ($unitId === null) {
                $notFound[] = $ref;
                continue;
            }

            $key  = strtolower($ref);
            $data = [];

            if ($pqCol !== null) {
                $val = $row[$pqCol] ?? '';
                if ($val !== null && $val !== '') {
                    $pq = (float) $val;
                    if ($pq >= 0 && $pq <= 100) {
                        $data['pq'] = $pq;
                    }
                }
            }

            if ($sectionCol !== null && isset($row[$sectionCol])) {
                $section = trim(strval($row[$sectionCol]));
                // Skip the "section = unit number" placeholder we emit on export.
                if ($section !== '' && strtolower($section) !== $key) {
                    $data['section'] = $section;
                }
            }

            // WeConnectU Ratio 1–5 (store exactly as supplied).
            foreach ($ratioCols as $i => $col) {
                if ($col === null) {
                    continue;
                }
                $val = trim(strval($row[$col] ?? ''));
                if ($val !== '' && is_numeric($val)) {
                    $data["ratio_{$i}"] = (float) $val;
                }
            }

            if ($unitSizeCol !== null) {
                $val = trim(strval($row[$unitSizeCol] ?? ''));
                if ($val !== '' && is_numeric($val)) {
                    $data['unit_size'] = (float) $val;
                }
            }

            if ($levyOverrideCol !== null) {
                $val = trim(strval($row[$levyOverrideCol] ?? ''));
                if ($val === '') {
                    $data['levy_override'] = null; // empty = clear override
                } else {
                    $override = (float) $val;
                    if ($override >= 0) {
                        $data['levy_override'] = $override;
                    }
                }
            }

            if (! empty($data)) {
                $updates[$key] = ['id' => $unitId, 'data' => $data];
            }
        }

        if (empty($updates)) {
            return response()->json([
                'message'   => 'No valid rows found in the file.',
                'updated'   => 0,
                'not_found' => $notFound,
            ], 422);
        }

        // Snapshot before-state for activity logging
        $unitIds      = array_column($updates, 'id');
        $beforeStates = Unit::whereIn('id', $unitIds)
            ->get(['id', 'pq', 'levy_override', 'organization_id'])
            ->keyBy('id');

        $updated = 0;
        DB::transaction(function () use ($updates, &$updated) {
            foreach ($updates as $row) {
                Unit::where('id', $row['id'])->update($row['data']);
                $updated++;
            }
        });

        // Log activity for each unit where pq or levy_override changed
        $user    = Auth::user();
        $batchId = (string) Str::uuid();
        foreach ($updates as $row) {
            $before     = $beforeStates->get($row['id']);
            $beforePq   = $before?->pq;
            $beforeOver = $before?->levy_override;
            $afterPq    = array_key_exists('pq', $row['data']) ? $row['data']['pq'] : $beforePq;
            $afterOver  = array_key_exists('levy_override', $row['data']) ? $row['data']['levy_override'] : $beforeOver;

            $changes = [];
            if ((string) $beforePq !== (string) $afterPq) {
                $changes[] = ['field' => 'PQ', 'old' => $beforePq, 'new' => $afterPq];
            }
            if ((string) $beforeOver !== (string) $afterOver) {
                $changes[] = ['field' => 'Levy Override', 'old' => $beforeOver, 'new' => $afterOver];
            }

            if (! empty($changes)) {
                UnitActivity::create([
                    'unit_id'         => $row['id'],
                    'organization_id' => $before?->organization_id,
                    'batch_id'        => $batchId,
                    'user_id'         => $user?->id,
                    'changed_by_name' => $user?->name ?? $user?->full_name ?? 'System',
                    'event'           => 'Updated unit details',
                    'category'        => 'unit',
                    'changes'         => $changes,
                ]);
            }
        }

        $message = "{$updated} unit" . ($updated !== 1 ? 's' : '') . ' updated successfully.';
        if (! empty($notFound)) {
            $message .= ' ' . count($notFound) . ' row(s) skipped — unit number not found in this community.';
        }

        return response()->json([
            'message'   => $message,
            'updated'   => $updated,
            'not_found' => $notFound,
        ]);
    }

    public function clearAll(Community $community)
    {
        $count = Unit::where('community_id', $community->id)
            ->where(function ($q) {
                $q->whereNotNull('pq')->orWhereNotNull('levy_override');
            })
            ->count();

        Unit::where('community_id', $community->id)->update(['pq' => null, 'levy_override' => null]);

        return response()->json([
            'message' => "PQs cleared for {$count} unit" . ($count !== 1 ? 's' : '') . '.',
            'cleared' => $count,
        ]);
    }

    private function parseCsv(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');
        $rowNum = 1;
        while (($line = fgetcsv($handle)) !== false) {
            $rows[$rowNum] = array_combine(
                range('A', chr(ord('A') + count($line) - 1)),
                $line
            );
            $rowNum++;
        }
        fclose($handle);
        return $rows;
    }
}
