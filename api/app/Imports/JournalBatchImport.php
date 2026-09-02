<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * A pass-through import used only with Excel::toArray() to read the raw rows of
 * an uploaded journal-batch spreadsheet. Rows are returned numerically indexed:
 *   [0] Ledger Type, [1] Account, [2] Description, [3] Debit, [4] Credit, [5] Balance
 * The service skips the heading row and maps the rest onto journal lines.
 */
class JournalBatchImport implements ToArray
{
    public function array(array $array): void
    {
        // No-op: Excel::toArray() returns the sheet rows directly.
    }
}
