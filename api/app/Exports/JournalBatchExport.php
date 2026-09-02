<?php

namespace App\Exports;

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\JournalBatch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * WeConnectU per-batch journal Excel export (the per-row download icon).
 *
 * Layout (matches WeConnectU exactly):
 *   Row 1  "Journal Batch {n}"                        — bold, larger
 *   Row 2  "Journal Batch - {date}"                   — bold
 *   Row 3  "Journal Group : {group}"                  — bold
 *   Row 4  column headings (Ledger Type / Account / Description / Debit / Credit / Balance)
 *   then one row per line:
 *     Ledger Type label · Account · Description · Debit · Credit · (Debit − Credit)
 *   final row: blank cells + the net balance (0.00 for a balanced batch)
 */
class JournalBatchExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private array $boldRows = [];
    private int $headingRow = 4;

    public function __construct(private readonly JournalBatch $batch)
    {
    }

    /**
     * @return array
     */
    public function array(): array
    {
        $b = $this->batch;

        $grid = [
            [$b->batch_name, '', '', '', '', ''],
            ['Journal Batch - ' . optional($b->date)->format('Y-m-d'), '', '', '', '', ''],
            ['Journal Group : ' . ($b->journal_group ?: ''), '', '', '', '', ''],
            [
                'Ledger Type (general / customer / supplier / reserve fund)',
                'Account  (e.g. 1000/001 or customer code)',
                'Description',
                'Debit',
                'Credit',
                'Balance (for checking purposes only)',
            ],
        ];

        $this->boldRows = [1, 2, 3, 4];

        $net = 0.0;

        foreach ($b->lines as $line) {
            $isDebit = $line->entry_type === JournalEntryType::DEBIT;
            $debit   = $isDebit ? (float) $line->amount : 0.0;
            $credit  = $isDebit ? 0.0 : (float) $line->amount;
            $net    += $debit - $credit;

            $grid[] = [
                $this->typeLabel($line->line_type),
                $this->accountLabel($line),
                (string) ($line->description ?? ''),
                round($debit, 2),
                round($credit, 2),
                round($debit - $credit, 2),
            ];
        }

        // Trailing net-balance row (0.00 when the batch balances).
        $grid[]           = ['', '', '', '', '', round($net, 2)];
        $this->boldRows[] = count($grid);

        return $grid;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 34, 'C' => 44, 'D' => 14, 'E' => 14, 'F' => 30];
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->getFont()->setSize(14);

                foreach ($this->boldRows as $row) {
                    $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
                }

                $last = count($this->array());
                $sheet->getStyle("D{$this->headingRow}:F{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    public function title(): string
    {
        return $this->batch->batch_name;
    }

    private function typeLabel(JournalLineType|string $type): string
    {
        $value = $type instanceof JournalLineType ? $type->value : $type;

        return match ($value) {
            JournalLineType::GENERAL->value      => 'General',
            JournalLineType::CUSTOMER->value     => 'Customer',
            JournalLineType::SUPPLIER->value     => 'Supplier',
            JournalLineType::RESERVE_FUND->value => 'Reserve Fund',
            default                              => ucfirst((string) $value),
        };
    }

    private function accountLabel($line): string
    {
        if ($line->ledger) {
            return (string) ($line->ledger->code ?: $line->ledger->name);
        }

        if ($line->unit) {
            return (string) ($line->unit->customer_code ?: $line->unit->unit_number);
        }

        return '';
    }
}
