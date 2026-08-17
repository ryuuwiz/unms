<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LaporanBillingExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        public ?string $status = null,
        public ?string $startDate = null,
        public ?string $endDate = null
    ) {}

    /**
     * @return Collection<int, Invoice>
     */
    public function collection(): Collection
    {
        return Invoice::with(['pelanggan', 'layananPelanggan.paketLayanan', 'promo'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal_terbit', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal_terbit', '<=', $this->endDate))
            ->orderByDesc('tanggal_terbit')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No. Invoice',
            'No. Registrasi',
            'Nama Pelanggan',
            'No. HP',
            'Site ID',
            'Paket Layanan',
            'Nominal Tagihan (Rp)',
            'Nominal Setelah Promo (Rp)',
            'Promo',
            'Status',
            'Tanggal Terbit',
            'Jatuh Tempo',
            'Tanggal Lunas',
            'Metode Pembayaran',
        ];
    }

    /**
     * @param  Invoice  $row
     */
    public function map($row): array
    {
        return [
            $row->no_invoice,
            $row->pelanggan?->no_reg ?? '-',
            $row->pelanggan?->nama_lengkap ?? '-',
            $row->pelanggan?->no_hp ?? '-',
            $row->layananPelanggan?->site_id ?? '-',
            $row->layananPelanggan?->paketLayanan?->nama_paket ?? '-',
            (float) $row->jumlah,
            (float) $row->jumlah_setelah_promo,
            $row->promo?->kode_promo ?? '-',
            $row->status->label(),
            $row->tanggal_terbit?->format('d/m/Y') ?? '-',
            $row->tanggal_jatuh_tempo?->format('d/m/Y') ?? '-',
            $row->tanggal_lunas?->format('d/m/Y') ?? '-',
            $row->metode_pembayaran?->label() ?? '-',
        ];
    }
}
