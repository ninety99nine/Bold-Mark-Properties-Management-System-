<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * A pass-through import used with Excel::toArray() to read the raw rows of an
 * uploaded spreadsheet (e.g. the Budget Excel Import). Rows come back numerically
 * indexed; the calling service skips the heading row and maps the rest.
 */
class RowsImport implements ToArray
{
    public function array(array $array): void
    {
        // No-op: Excel::toArray() returns the sheet rows directly.
    }
}
