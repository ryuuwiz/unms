<?php

namespace App\Exports;

use App\Models\Barang;
use App\Models\BarangKeluar;
use App\Models\BarangMasuk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DataBarangExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
    ) {}

    /**
     * @return Collection<int, Barang>
     */
    public function collection(): Collection
    {
        $masukSebelum = BarangMasuk::query()
            ->where('tanggal', '<', $this->startDate->toDateString())
            ->selectRaw('barang_id, SUM(jumlah_masuk) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id');

        $keluarSebelum = BarangKeluar::query()
            ->where('tanggal', '<', $this->startDate->toDateString())
            ->selectRaw('barang_id, SUM(jumlah_keluar) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id');

        $masukPeriode = BarangMasuk::query()
            ->whereBetween('tanggal', [$this->startDate->toDateString(), $this->endDate->toDateString()])
            ->selectRaw('barang_id, SUM(jumlah_masuk) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id');

        $keluarPeriode = BarangKeluar::query()
            ->whereBetween('tanggal', [$this->startDate->toDateString(), $this->endDate->toDateString()])
            ->selectRaw('barang_id, SUM(jumlah_keluar) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id');

        return Barang::query()
            ->orderBy('nama_barang')
            ->get()
            ->map(function (Barang $barang) use ($masukSebelum, $keluarSebelum, $masukPeriode, $keluarPeriode) {
                $stokAwal = (int) ($masukSebelum[$barang->id] ?? 0) - (int) ($keluarSebelum[$barang->id] ?? 0);
                $masuk = (int) ($masukPeriode[$barang->id] ?? 0);
                $keluar = (int) ($keluarPeriode[$barang->id] ?? 0);

                $barang->setAttribute('stok_awal_periode', $stokAwal);
                $barang->setAttribute('masuk_periode', $masuk);
                $barang->setAttribute('keluar_periode', $keluar);
                $barang->setAttribute('stok_akhir_periode', $stokAwal + $masuk - $keluar);

                return $barang;
            });
    }

    public function headings(): array
    {
        return [
            'KODE BARANG',
            'NAMA BARANG',
            'STOK AWAL',
            'MASUK',
            'KELUAR',
            'STOK AKHIR',
        ];
    }

    /**
     * @param  Barang  $row
     */
    public function map($row): array
    {
        return [
            $row->kode_barang,
            $row->nama_barang,
            $row->getAttribute('stok_awal_periode'),
            $row->getAttribute('masuk_periode'),
            $row->getAttribute('keluar_periode'),
            $row->getAttribute('stok_akhir_periode'),
        ];
    }
}
