<?php

namespace App\Exports;

use App\Models\LayananPelanggan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DataPelangganPerPaketExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        public ?int $paketLayananId = null,
        public ?string $status = null,
    ) {}

    /**
     * @return Collection<int, LayananPelanggan>
     */
    public function collection(): Collection
    {
        return LayananPelanggan::with(['pelanggan', 'paketLayanan'])
            ->when($this->paketLayananId, fn ($q) => $q->where('paket_layanan_id', $this->paketLayananId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'No. Registrasi',
            'Nama Pelanggan',
            'No. HP',
            'Paket Layanan',
            'Status Layanan',
            'Tanggal Mulai',
            'Tanggal Expired',
        ];
    }

    /**
     * @param  LayananPelanggan  $row
     */
    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->pelanggan?->no_reg ?? '-',
            $row->pelanggan?->nama_lengkap ?? '-',
            $row->pelanggan?->no_hp ?? '-',
            $row->paketLayanan?->nama_paket ?? '-',
            $row->statusBadgeLabel(),
            $row->tanggal_mulai?->format('d/m/Y') ?? '-',
            $row->tanggal_expired?->format('d/m/Y') ?? '-',
        ];
    }
}
