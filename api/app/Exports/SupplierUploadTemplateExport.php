<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * WeConnectU "Upload Suppliers" blank template xlsx (the Download Template
 * link). Data starts on row 2.
 *
 * Exact headers, in order:
 *   Code · Supplier Name · Account Number · Branch Code · Bank Name ·
 *   Account Type · Email Address · Telephone Number · VAT Registration Number
 */
class SupplierUploadTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @return array
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Code',
            'Supplier Name',
            'Account Number',
            'Branch Code',
            'Bank Name',
            'Account Type',
            'Email Address',
            'Telephone Number',
            'VAT Registration Number',
        ];
    }
}
