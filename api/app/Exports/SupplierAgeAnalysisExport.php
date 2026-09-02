<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Byte-faithful WeConnectU "Supplier Age Analysis" Excel export.
 *
 * WeConnectU reuses the exact same 8-column (A:H) template as the Customer Age
 * Analysis — including the leading "Unit No" column, which is blank for
 * suppliers (the supplier name sits in column B "Supplier"). Verified against a
 * real WeConnectU export ("supplier age analysis-barnato view body corporate-…").
 *
 * Layout:
 *   Row 1  Community name         — 18pt, #dddddd fill across A:H
 *   Row 2  "Supplier Age Analysis" — 16pt, #eeeeee fill across A:H
 *   Row 3  "Report Date: {ageing date}" — 14pt
 *   Row 4  "Generated: {today}"   — 11pt
 *   Row 5  (blank)
 *   Row 6  Column headings        — bold, #eeeeee, right-aligned B:H
 *   Row 7+ Data rows              — raw numeric bucket/balance values (A blank)
 *   Last   Totals row             — 12pt bold, #eeeeee
 *
 * Columns: Unit No | Supplier | 120+ Days | 90+ Days | 60+ Days | 30+ Days | Current | Balance.
 * The worksheet tab keeps WeConnectU's default name ("Worksheet").
 */
class SupplierAgeAnalysisExport implements FromArray, WithColumnWidths, WithEvents, WithStrictNullComparison
{
    public function __construct(
        private string $communityName,
        private string $ageingDate,
        private array $rows,
        private array $totals,
    ) {
    }

    /**
     * Build the full worksheet grid, header block through totals.
     *
     * @return array
     */
    public function array(): array
    {
        $grid = [
            [$this->communityName],
            ['Supplier Age Analysis'],
            ['Report Date: ' . $this->ageingDate],
            ['Generated: ' . now()->format('Y-m-d')],
            ['', '', '', '', '', '', '', ''], // spacer row (kept non-empty so it is not dropped)
            ['Unit No', 'Supplier', '120+ Days', '90+ Days', '60+ Days', '30+ Days', 'Current', 'Balance'],
        ];

        foreach ($this->rows as $r) {
            $grid[] = [
                '',
                $r['label'],
                (float) $r['120_plus'],
                (float) $r['90_days'],
                (float) $r['60_days'],
                (float) $r['30_days'],
                (float) $r['current'],
                (float) $r['balance'],
            ];
        }

        $grid[] = [
            '',
            'Totals',
            (float) ($this->totals['120_plus'] ?? 0),
            (float) ($this->totals['90_days'] ?? 0),
            (float) ($this->totals['60_days'] ?? 0),
            (float) ($this->totals['30_days'] ?? 0),
            (float) ($this->totals['current'] ?? 0),
            (float) ($this->totals['balance'] ?? 0),
        ];

        return $grid;
    }

    /**
     * WeConnectU column widths: Unit No 20, Supplier 40, money columns 20.
     *
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20, 'B' => 40, 'C' => 20, 'D' => 20,
            'E' => 20, 'F' => 20, 'G' => 20, 'H' => 20,
        ];
    }

    /**
     * Apply the header-block, heading-row and totals-row styling.
     *
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $totalsRow = 6 + count($this->rows) + 1;

                // Row 1 — community name (18pt) on a #dddddd band.
                $sheet->getStyle('A1:H1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');
                $sheet->getStyle('A1')->getFont()->setSize(18);

                // Row 2 — report title (16pt) on a #eeeeee band.
                $sheet->getStyle('A2:H2')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
                $sheet->getStyle('A2')->getFont()->setSize(16);

                // Row 3 — report date (14pt).
                $sheet->getStyle('A3')->getFont()->setSize(14);

                // Row 6 — bold headings, #eeeeee band, money columns right-aligned.
                $sheet->getStyle('A6:H6')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
                $sheet->getStyle('A6:H6')->getFont()->setBold(true);
                $sheet->getStyle('B6:H6')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Totals row — 12pt bold on a #eeeeee band.
                $sheet->getStyle("A{$totalsRow}:H{$totalsRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
                $sheet->getStyle("A{$totalsRow}:H{$totalsRow}")->getFont()
                    ->setBold(true)->setSize(12);
            },
        ];
    }
}
