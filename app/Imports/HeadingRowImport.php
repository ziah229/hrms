<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Membaca sheet pertama sebagai koleksi baris ber-key heading (snake_case).
 * Pemakaian: Excel::toCollection(new HeadingRowImport, $file)->first()
 */
class HeadingRowImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $collection): void
    {
        //
    }
}
