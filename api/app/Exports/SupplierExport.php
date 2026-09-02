<?php

namespace App\Exports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * WeConnectU "Export Suppliers" xlsx (the Export button on the Supplier List).
 *
 * Exact headers, in order:
 *   Code · Supplier Name · Account Number · Branch Code · Email Address · Contact Number
 * (Contact Number = the supplier's phone.)
 */
class SupplierExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param Collection<int,Supplier> $suppliers
     */
    public function __construct(private readonly Collection $suppliers)
    {
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return $this->suppliers->map(fn (Supplier $s): array => [
            $s->supplier_code,
            $s->name,
            $s->account_number,
            $s->branch_code,
            $s->email,
            $s->phone,
        ])->all();
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return ['Code', 'Supplier Name', 'Account Number', 'Branch Code', 'Email Address', 'Contact Number'];
    }
}
