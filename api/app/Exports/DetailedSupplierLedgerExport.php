<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * WeConnectU "Detailed Supplier Ledger" Excel export.
 *
 * Layout (matches WeConnectU exactly):
 *   Row 1  Community name              — 16pt bold, #dddddd fill across A:G
 *   Row 2  "Detailed Supplier Ledger"  — 13pt bold
 *   Row 3  "Report Date: {from} to {to}"
 *   Row 4  "Generated: {today}"
 *   Row 5  (blank)
 *   then, per supplier:
 *     heading "CODE - Name"            — bold, #dddddd fill, bordered
 *     column headings                  — bold, #eeeeee fill, bordered
 *     transaction rows                 — bordered, Debit/Credit/Balance right-aligned
 *     totals row                       — bold, #eeeeee fill, bordered
 *     (blank spacer)
 */
class DetailedSupplierLedgerExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private array $boldRows     = [];
    private array $fillDark     = [];   // section heading rows (#dddddd)
    private array $fillLight    = [];   // column-header + totals rows (#eeeeee)
    private array $borderBlocks = [];   // [startRow, endRow] per supplier section
    private int   $lastRow      = 0;

    public function __construct(
        private string $communityName,
        private string $from,
        private string $to,
        private array $ledgers,
    ) {
    }

    /**
     * Build the full worksheet grid, recording which rows to style.
     *
     * @return array
     */
    public function array(): array
    {
        $grid = [
            [$this->communityName, '', '', '', '', '', ''],
            ['Detailed Supplier Ledger', '', '', '', '', '', ''],
            ['Report Date: ' . $this->from . ' to ' . $this->to, '', '', '', '', '', ''],
            ['Generated: ' . now()->format('Y-m-d'), '', '', '', '', '', ''],
            ['', '', '', '', '', '', ''],
        ];
        $this->boldRows[] = 1;
        $this->fillDark[] = 1;
        $this->boldRows[] = 2;

        foreach ($this->ledgers as $ledger) {
            $blockStart = count($grid) + 1;

            $grid[]           = [$ledger['heading'], '', '', '', '', '', ''];
            $this->boldRows[] = count($grid);
            $this->fillDark[] = count($grid);

            $grid[]            = ['Date', 'Source', 'Description', 'Remarks', 'Debit', 'Credit', 'Balance'];
            $this->boldRows[]  = count($grid);
            $this->fillLight[] = count($grid);

            foreach ($ledger['rows'] as $r) {
                $grid[] = [
                    $r['date'],
                    $r['source'],
                    $r['description'],
                    $r['remarks'],
                    (float) $r['debit'],
                    (float) $r['credit'],
                    (float) $r['balance'],
                ];
            }

            $grid[]            = ['', '', '', '', (float) $ledger['totals']['debit'], (float) $ledger['totals']['credit'], (float) $ledger['totals']['balance']];
            $this->boldRows[]  = count($grid);
            $this->fillLight[] = count($grid);

            $this->borderBlocks[] = [$blockStart, count($grid)];

            $grid[] = ['', '', '', '', '', '', '']; // spacer between suppliers
        }

        $this->lastRow = count($grid);

        return $grid;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 34, 'C' => 30, 'D' => 22, 'E' => 14, 'F' => 14, 'G' => 16];
    }

    /**
     * Apply WeConnectU styling: fills, borders, bold rows and right-aligned numbers.
     *
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->getFont()->setSize(16);
                $sheet->getStyle('A2')->getFont()->setSize(13);

                foreach ($this->boldRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                }

                foreach ($this->fillDark as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');
                }
                foreach ($this->fillLight as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
                }

                // Thin borders around each supplier's ledger block (heading → totals).
                foreach ($this->borderBlocks as [$start, $end]) {
                    $sheet->getStyle("A{$start}:G{$end}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CCCCCC');
                }

                // Right-align the numeric columns (Debit / Credit / Balance).
                $sheet->getStyle("E1:G{$this->lastRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    public function title(): string
    {
        return 'Detailed Supplier Ledger';
    }
}
