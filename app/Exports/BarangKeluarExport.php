<?php

namespace App\Exports;

use App\Models\BarangKeluar;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BarangKeluarExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        public ?string $search = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}

    /**
     * @return Collection<int, BarangKeluar>
     */
    public function collection(): Collection
    {
        return BarangKeluar::with('barang')
            ->when($this->search, fn ($q) => $q->whereHas('barang', fn ($bq) => $bq->search($this->search)))
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NO',
            'TANGGAL/BULAN',
            'KODE BARANG',
            'NAMA BARANG',
            'JUMLAH KELUAR',
            'KETERANGAN',
            'TEKNISI',
        ];
    }

    /**
     * @param  BarangKeluar  $row
     */
    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->tanggal->format('d/m/Y'),
            $row->barang?->kode_barang ?? '-',
            $row->barang?->nama_barang ?? '-',
            $row->jumlah_keluar,
            $row->keterangan ?? '-',
            $row->teknisi ?? '-',
        ];
    }
}
