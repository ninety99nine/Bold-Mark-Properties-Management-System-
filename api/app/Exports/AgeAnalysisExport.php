<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Byte-faithful WeConnectU "Customer Age Analysis" Excel export.
 *
 * Layout (matches WeConnectU exactly):
 *   Row 1  Community name        — 18pt, #dddddd fill across A:H
 *   Row 2  "Customer Age Analysis" — 16pt, #eeeeee fill across A:H
 *   Row 3  "Report Date: {ageing date}" — 14pt
 *   Row 4  "Generated: {today}"  — 11pt (default)
 *   Row 5  (blank)
 *   Row 6  Column headings       — bold, #eeeeee, right-aligned B:H
 *   Row 7+ Data rows             — raw numeric bucket/balance values
 *   Last   Totals row            — 12pt bold, #eeeeee
 *
 * Columns: Unit No | Customer | 120+ Days | 90+ Days | 60+ Days | 30+ Days | Current | Balance.
 * (No "Status" column — WeConnectU's export omits it.)
 */
class AgeAnalysisExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
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
            ['Customer Age Analysis'],
            ['Report Date: ' . $this->ageingDate],
            ['Generated: ' . now()->format('Y-m-d')],
            ['', '', '', '', '', '', '', ''], // spacer row (kept non-empty so it is not dropped)
            ['Unit No', 'Customer', '120+ Days', '90+ Days', '60+ Days', '30+ Days', 'Current', 'Balance'],
        ];

        foreach ($this->rows as $r) {
            $grid[] = [
                $r['unit_no'],
                trim(($r['customer_code'] ? $r['customer_code'] . ': ' : '') . ($r['customer_name'] ?? '')),
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
     * WeConnectU column widths: Unit No 20, Customer 40, money columns 20.
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
     * The worksheet tab name.
     *
     * @return string
     */
    public function title(): string
    {
        return 'Customer Age Analysis';
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
