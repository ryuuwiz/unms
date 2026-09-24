<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class LaporanBillingExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(
        public ?string $status = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public bool $sertakanNik = false,
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
            'NIK',
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
            $row->pelanggan->no_reg ?? '-',
            $row->pelanggan?->namaLengkap() ?? '-',
            $this->sertakanNik ? ($row->pelanggan->nik ?? '-') : '-',
            $row->pelanggan->no_hp ?? '-',
            $row->layananPelanggan->site_id ?? '-',
            $row->layananPelanggan?->paketLayanan->nama_paket ?? '-',
            (float) $row->jumlah,
            (float) $row->jumlah_setelah_promo,
            $row->promo->kode_promo ?? '-',
            $row->status->label(),
            $row->tanggal_terbit->format('d/m/Y'),
            $row->tanggal_jatuh_tempo->format('d/m/Y'),
            $row->tanggal_lunas?->format('d/m/Y') ?? '-',
            $row->metode_pembayaran?->label() ?? '-',
        ];
    }

    /**
     * NIK & No. HP ditulis sebagai teks: 16 digit NIK melebihi presisi angka Excel (digit akhir
     * berubah jadi 0) dan nol di depan No. HP hilang bila dibiarkan jadi angka.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (in_array($cell->getColumn(), ['D', 'E'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
