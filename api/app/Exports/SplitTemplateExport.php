<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * WeConnectU cashbook split-allocation upload template (the "Download Template"
 * link in the split modal).
 *
 * Layout:
 *   Row 1  column headings (Ledger Type / Account / Remarks / Amount / Total)
 *   Row 2  one worked example line
 *   The E (Total) column carries a running SUM used only for checking that the
 *   split lines add up to the bank line amount.
 */
class SplitTemplateExport implements FromArray, WithColumnWidths, WithEvents
{
    /** Column headings for the split-allocation upload template. */
    private const HEADINGS = [
        'Ledger Type (general / customer / supplier / reserve)',
        'Account (e.g. 1000/001 or customer code)',
        'Remarks',
        'Amount (Receipts(+)/Payments(-))',
        'Total (for checking purposes only)',
    ];

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            self::HEADINGS,
            ['general', '1000/001', 'Levy portion', 800, '=SUM(D2:D2)'],
            ['customer', 'DIR001-U1', 'Balance to customer', 200, '=SUM(D2:D3)'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 46, 'B' => 34, 'C' => 30, 'D' => 26, 'E' => 30];
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:E1')->getFont()->setBold(true);
                $sheet->getStyle('D1:E3')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
