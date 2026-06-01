<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Estate;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitPqController extends Controller
{
    /**
     * Export a PQ spreadsheet for all units in an estate.
     * Existing values are pre-filled so the manager only needs to update.
     * Supports ?format=csv or ?format=xlsx (default).
     */
    public function export(Request $request, Estate $estate)
    {
        $format = strtolower($request->query('format', 'xlsx'));

        $orderRaw = DB::getDriverName() === 'sqlite'
            ? 'unit_number asc'
            : "CASE WHEN REGEXP_REPLACE(unit_number, '[^0-9]', '') = '' THEN 1 ELSE 0 END, CAST(NULLIF(REGEXP_REPLACE(unit_number, '[^0-9]', ''), '') AS UNSIGNED), unit_number";

        $units = Unit::where('estate_id', $estate->id)
            ->orderByRaw($orderRaw)
            ->get(['unit_number', 'section', 'pq', 'levy_override']);

        $slug = 'pq-' . str_replace(' ', '-', strtolower($estate->name));

        if ($format === 'csv') {
            $rows = [['unit_number', 'section', 'pq', 'levy_override']];
            foreach ($units as $unit) {
                $rows[] = [$unit->unit_number, $unit->section ?? '', $unit->pq ?? '', $unit->levy_override ?? ''];
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

        // XLSX
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PQ Data');

        // Header row
        $sheet->setCellValue('A1', 'unit_number');
        $sheet->setCellValue('B1', 'section');
        $sheet->setCellValue('C1', 'pq');
        $sheet->setCellValue('D1', 'levy_override');

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        foreach ($units as $i => $unit) {
            $row = $i + 2;
            $sheet->setCellValue("A{$row}", $unit->unit_number);
            $sheet->setCellValue("B{$row}", $unit->section ?? '');
            $sheet->setCellValue("C{$row}", $unit->pq ?? '');
            $sheet->setCellValue("D{$row}", $unit->levy_override ?? '');
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
    public function parse(Request $request, Estate $estate)
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
    public function import(Request $request, Estate $estate)
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

        if (! isset($colMap['unit_number'])) {
            return response()->json(['message' => 'The file must contain a "unit_number" column.', 'updated' => 0], 422);
        }

        $unitNumberCol   = $colMap['unit_number'];
        $pqCol           = $colMap['pq'] ?? null;
        $sectionCol      = $colMap['section'] ?? null;
        $levyOverrideCol = $colMap['levy_override'] ?? null;

        $estateUnits = Unit::where('estate_id', $estate->id)
            ->pluck('id', 'unit_number')
            ->mapWithKeys(fn($id, $num) => [strtolower(trim($num)) => $id]);

        $updates  = [];
        $notFound = [];

        foreach (array_slice($rows, 1, null, true) as $row) {
            $unitNumber = trim(strval($row[$unitNumberCol] ?? ''));
            if ($unitNumber === '') {
                continue;
            }

            $key = strtolower($unitNumber);

            if (! isset($estateUnits[$key])) {
                $notFound[] = $unitNumber;
                continue;
            }

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
                $data['section'] = trim(strval($row[$sectionCol]));
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
                $updates[$key] = ['id' => $estateUnits[$key], 'data' => $data];
            }
        }

        if (empty($updates)) {
            return response()->json([
                'message'   => 'No valid rows found in the file.',
                'updated'   => 0,
                'not_found' => $notFound,
            ], 422);
        }

        $updated = 0;
        DB::transaction(function () use ($updates, &$updated) {
            foreach ($updates as $row) {
                Unit::where('id', $row['id'])->update($row['data']);
                $updated++;
            }
        });

        $message = "{$updated} unit" . ($updated !== 1 ? 's' : '') . ' updated successfully.';
        if (! empty($notFound)) {
            $message .= ' ' . count($notFound) . ' row(s) skipped — unit number not found in this estate.';
        }

        return response()->json([
            'message'   => $message,
            'updated'   => $updated,
            'not_found' => $notFound,
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
