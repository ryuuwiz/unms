<?php

namespace App\Imports\Barang;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Berkas Impor Inventaris: 3 sheet dicocokkan per nama, bukan urutan -- lihat CONTEXT.md "Impor Inventaris".
 */
class InventarisImport implements Import, SkipsUnknownSheets, WithMultipleSheets
{
    public const SHEET_BARANG = 'Data Barang';

    public const SHEET_MASUK = 'Barang Masuk';

    public const SHEET_KELUAR = 'Barang Keluar';

    public BarisSheetImport $barang;

    public BarisSheetImport $masuk;

    public BarisSheetImport $keluar;

    public function __construct()
    {
        $this->barang = new BarisSheetImport;
        $this->masuk = new BarisSheetImport;
        $this->keluar = new BarisSheetImport;
    }

    /**
     * @return array<string, BarisSheetImport>
     */
    public function sheets(): array
    {
        return [
            self::SHEET_BARANG => $this->barang,
            self::SHEET_MASUK => $this->masuk,
            self::SHEET_KELUAR => $this->keluar,
        ];
    }

    /**
     * Sheet hilang dilaporkan oleh ImporInventarisService lewat `baris === null`.
     *
     * @param  int|string  $sheetName
     */
    public function onUnknownSheet($sheetName): void {}
}
