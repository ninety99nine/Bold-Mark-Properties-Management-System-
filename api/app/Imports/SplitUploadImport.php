<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * A pass-through import used only with Excel::toArray() to read the raw rows of
 * an uploaded cashbook split spreadsheet. Rows are returned numerically indexed:
 *   [0] Ledger Type, [1] Account, [2] Remarks, [3] Amount, [4] Total
 * The service skips the heading row and resolves the rest into split lines.
 */
class SplitUploadImport implements ToArray
{
    public function array(array $array): void
    {
        // No-op: Excel::toArray() returns the sheet rows directly.
    }
}
