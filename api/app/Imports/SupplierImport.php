<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * A pass-through import used only with Excel::toArray() to read the raw rows of
 * an uploaded supplier spreadsheet. Rows are returned numerically indexed:
 *   [0] Code, [1] Supplier Name, [2] Account Number, [3] Branch Code,
 *   [4] Bank Name, [5] Account Type, [6] Email Address, [7] Telephone Number,
 *   [8] VAT Registration Number
 * The service skips the heading row and upserts the rest.
 */
class SupplierImport implements ToArray
{
    public function array(array $array): void
    {
        // No-op: Excel::toArray() returns the sheet rows directly.
    }
}
