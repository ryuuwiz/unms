<?php

namespace App\Exports;

use App\Models\JenisBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Data Barang: Stok Periode sebulan per jenis barang -- lihat CONTEXT.md "Stok Periode".
 */
class DataBarangExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        public Carbon $bulan,
        public string $search = '',
        public ?int $kategoriId = null,
        public ?int $stokMaks = null,
        public string $urut = 'nama',
    ) {}

    /**
     * Dipakai juga oleh halaman Data Barang supaya layar dan berkas ekspor selalu sama.
     *
     * @return Builder<JenisBarang>
     */
    public function query(): Builder
    {
        return JenisBarang::stokPeriode($this->bulan)
            ->with('kategori')
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('nama', 'like', "%{$this->search}%")
                ->orWhere('kode', 'like', "%{$this->search}%")))
            ->when($this->kategoriId, fn (Builder $q) => $q->where('kategori_barang_id', $this->kategoriId))
            ->when($this->stokMaks !== null, fn (Builder $q) => $q->where('stok_akhir', '<=', $this->stokMaks))
            ->when($this->urut === 'stok_asc', fn (Builder $q) => $q->orderBy('stok_akhir'))
            ->when($this->urut === 'stok_desc', fn (Builder $q) => $q->orderByDesc('stok_akhir'))
            ->orderBy('nama');
    }

    public function title(): string
    {
        return 'Stok '.$this->bulan->translatedFormat('F Y');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Kode Barang', 'Nama Barang', 'Kategori', 'Satuan', 'Stok Awal', 'Masuk', 'Keluar', 'Stok Akhir'];
    }

    /**
     * @param  JenisBarang  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->kode,
            $row->nama,
            $row->kategori->nama,
            $row->satuan,
            $row->stok_awal,
            $row->jumlah_masuk,
            $row->jumlah_keluar,
            $row->stok_akhir,
        ];
    }
}
