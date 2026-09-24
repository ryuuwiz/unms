<?php

namespace App\Imports\Barang;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Penampung baris mentah satu sheet (header di baris 1, di-slug oleh Laravel Excel:
 * "KODE BARANG" -> `kode_barang`, "TANGGAL/BULAN" -> `tanggalbulan`).
 */
class BarisSheetImport implements ToArray, WithHeadingRow
{
    /** @var list<array<string, mixed>>|null Null bila sheet tidak ada di berkas. */
    public ?array $baris = null;

    /**
     * @param  array<int, array<string, mixed>>  $array
     */
    public function array(array $array): void
    {
        $this->baris = array_values(array_filter(
            $array,
            fn (array $baris) => collect($baris)->contains(fn ($nilai) => $nilai !== null && trim((string) $nilai) !== ''),
        ));
    }
}
