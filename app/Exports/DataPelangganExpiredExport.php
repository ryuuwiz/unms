<?php

namespace App\Exports;

use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DataPelangganExpiredExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @return Collection<int, LayananPelanggan>
     */
    public function collection(): Collection
    {
        return LayananPelanggan::with(['pelanggan', 'paketLayanan'])
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend])
            ->expiredSebelum(Carbon::now())
            ->orderBy('tanggal_expired')
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
            'Tanggal Expired',
            'Hari Terlambat',
        ];
    }

    /**
     * @param  LayananPelanggan  $row
     */
    public function map($row): array
    {
        static $no = 0;
        $no++;

        $hariTerlambat = $row->tanggal_expired ? $row->tanggal_expired->diffInDays(Carbon::now()) : 0;

        return [
            $no,
            $row->pelanggan?->no_reg ?? '-',
            $row->pelanggan?->nama_lengkap ?? '-',
            $row->pelanggan?->no_hp ?? '-',
            $row->paketLayanan?->nama_paket ?? '-',
            $row->statusBadgeLabel(),
            $row->tanggal_expired?->format('d/m/Y') ?? '-',
            $hariTerlambat,
        ];
    }
}
