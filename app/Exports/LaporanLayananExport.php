<?php

namespace App\Exports;

use App\Models\LayananPelanggan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Ekspor Data Layanan: satu baris per Data Registrasi Billing -- lihat CONTEXT.md "Ekspor Data Layanan".
 */
class LaporanLayananExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(
        public ?int $paketLayananId = null,
        public ?string $status = null,
        public bool $hanyaExpired = false,
    ) {}

    /**
     * Dipakai juga oleh halaman Laporan Layanan untuk pratinjau, supaya filter di layar dan di
     * berkas ekspor tidak pernah berbeda.
     *
     * @return Builder<LayananPelanggan>
     */
    public function query(): Builder
    {
        return LayananPelanggan::query()
            ->with(['pelanggan', 'paketLayanan', 'router', 'ipPubliks'])
            ->when($this->paketLayananId, fn (Builder $q) => $q->where('paket_layanan_id', $this->paketLayananId))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->hanyaExpired, fn (Builder $q) => $q->whereNotNull('tanggal_expired')->where('tanggal_expired', '<', Carbon::today()))
            ->orderBy('paket_layanan_id')
            ->orderBy('tanggal_expired');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No. Registrasi',
            'Nama Pelanggan',
            'No. HP',
            'Site ID',
            'Paket Layanan',
            'Harga (Rp)',
            'Router',
            'Status Layanan',
            'Tanggal Mulai',
            'Tanggal Expired',
            'Hari Lewat Expired',
            'Alamat Pemasangan',
        ];
    }

    /**
     * @param  LayananPelanggan  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $expired = $row->tanggal_expired;
        $hariLewat = $expired && $expired->lt(Carbon::today()) ? (int) $expired->diffInDays(Carbon::today()) : null;

        return [
            $row->pelanggan->no_reg ?? '-',
            $row->pelanggan?->namaLengkap() ?? '-',
            $row->pelanggan->no_hp ?? '-',
            $row->site_id,
            $row->paketLayanan->nama_paket ?? '-',
            $row->hargaDasar() + $row->hargaTambahan(),
            $row->router->nama_router ?? '-',
            $row->status->label(),
            $row->tanggal_mulai->format('d/m/Y'),
            $expired?->format('d/m/Y') ?? '-',
            $hariLewat ?? '-',
            $row->alamat_pemasangan ?? '-',
        ];
    }

    /**
     * No. HP ditulis sebagai teks agar nol di depan tidak hilang.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getColumn() === 'C') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
