<?php

namespace App\Exports\Barang;

use App\Services\Barang\ImporInventarisService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Template 3 sheet untuk Impor Inventaris -- lihat CONTEXT.md "Impor Inventaris".
 */
class TemplateImporInventarisExport implements Export, WithMultipleSheets
{
    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        return [
            $this->sheet(ImporInventarisService::SHEET_BARANG,
                ['KODE BARANG', 'NAMA BARANG', 'STOK AWAL', 'MASUK', 'KELUAR', 'STOK AKHIR', 'KATEGORI', 'SATUAN', 'DILACAK'],
                [
                    ['KBL-DROP', 'Kabel Drop Core 1', 1000, 500, 300, 1200, 'KBL', 'meter', 'N'],
                    ['MDM-F609', 'Modem ZTE F609', 0, 2, 1, 1, 'MDM', 'unit', 'Y'],
                ]),
            $this->sheet(ImporInventarisService::SHEET_MASUK,
                ['NO', 'TANGGAL', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH MASUK', 'TIPE', 'KETERANGAN'],
                [
                    [1, '01/09/2026', 'KBL-DROP', 'Kabel Drop Core 1', 500, 'PEMBELIAN', ''],
                    [2, '01/09/2026', 'MDM-NEW-BF-240', 'Modem ZTE F609', 1, 'SALDO AWAL', 'Stok lama gudang'],
                    [3, '05/09/2026', 'MDM-NEW-BF-241', 'Modem ZTE F609', 1, 'PEMBELIAN', ''],
                ]),
            $this->sheet(ImporInventarisService::SHEET_KELUAR,
                ['NO', 'TANGGAL/BULAN', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH KELUAR', 'KETERANGAN', 'TEKNIS'],
                [
                    [1, '09/2026', 'KBL-DROP', 'Kabel Drop Core 1', 300, 'Pemasangan pelanggan', 'Nama Teknisi'],
                    [2, '10/09/2026', 'MDM-NEW-BF-240', 'Modem ZTE F609', 1, 'Pemasangan TCK-2026-000001', 'Nama Teknisi'],
                ]),
        ];
    }

    /**
     * @param  list<string>  $judul
     * @param  list<list<mixed>>  $contoh
     */
    private function sheet(string $nama, array $judul, array $contoh): object
    {
        return new class($nama, $judul, $contoh) implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
        {
            /**
             * @param  list<string>  $judul
             * @param  list<list<mixed>>  $contoh
             */
            public function __construct(private string $nama, private array $judul, private array $contoh) {}

            /**
             * @return list<list<mixed>>
             */
            public function array(): array
            {
                return $this->contoh;
            }

            /**
             * @return list<string>
             */
            public function headings(): array
            {
                return $this->judul;
            }

            public function title(): string
            {
                return $this->nama;
            }
        };
    }
}
