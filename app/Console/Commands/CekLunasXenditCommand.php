<?php

namespace App\Console\Commands;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\Enums\AksiPelunasanSusulan;
use App\Services\PaymentGateway\LaporanPelunasanSusulan;
use App\Services\PaymentGateway\PelunasanSusulan;
use App\Support\Rupiah;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CekLunasXenditCommand extends Command
{
    /** Jendela harian otomatis; riwayat lebih lama lewat --dari. */
    public const HARI_JENDELA = 35;

    /**
     * @var string
     */
    protected $signature = 'pembayaran:cek-lunas-xendit
                            {--dari= : Tanggal awal waktu bayar di Xendit (Y-m-d, WIB), default 35 hari lalu}
                            {--dry-run : Tampilkan yang akan dilunasi tanpa mengubah data}';

    /**
     * @var string
     */
    protected $description = 'Pelunasan Susulan: lunasi invoice yang sudah PAID di Xendit tetapi belum Lunas di sistem (ADR-0069)';

    public function handle(PelunasanSusulan $pelunasanSusulan, LaporanPelunasanSusulan $laporan): int
    {
        $zonaWaktu = config('app.zona_waktu_bisnis');
        $sejak = $this->option('dari')
            ? Carbon::parse((string) $this->option('dari'), $zonaWaktu)->startOfDay()
            : now($zonaWaktu)->subDays(self::HARI_JENDELA)->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Memeriksa pembayaran PAID di Xendit sejak {$sejak->toDateString()}...");

        $hasil = $pelunasanSusulan->jalankan($sejak, $dryRun);
        if (! $dryRun) {
            $laporan->laporkan($hasil);
        }

        $this->tampilkanTabel($hasil);
        $this->tampilkanRingkasan($hasil);

        return self::SUCCESS;
    }

    /**
     * @param  list<HasilPelunasanSusulan>  $hasil
     */
    protected function tampilkanTabel(array $hasil): void
    {
        $baris = collect($hasil)
            ->reject(fn (HasilPelunasanSusulan $item): bool => $item->aksi === AksiPelunasanSusulan::SudahTercatat)
            ->map(fn (HasilPelunasanSusulan $item): array => [
                $item->koneksi,
                $item->invoice?->no_invoice ?? $item->pembayaran?->externalId ?? '-',
                $item->invoice?->pelanggan?->namaLengkap() ?? '-',
                $item->pembayaran ? Rupiah::format($item->pembayaran->paidAmount) : '-',
                $item->pembayaran?->paidAt ?? '-',
                $item->statusSebelum ?? '-',
                $item->aksi->value,
                $item->keterangan,
            ]);

        if ($baris->isNotEmpty()) {
            $this->table(['Koneksi', 'No Invoice', 'Pelanggan', 'Nominal', 'Dibayar', 'Status Lokal', 'Aksi', 'Keterangan'], $baris->all());
        }
    }

    /**
     * @param  list<HasilPelunasanSusulan>  $hasil
     */
    protected function tampilkanRingkasan(array $hasil): void
    {
        $jumlah = collect($hasil)->countBy(fn (HasilPelunasanSusulan $item): string => $item->aksi->value);

        $this->info(sprintf(
            'Selesai. %d dilunasi, %d akan dilunasi (dry run), %d dilaporkan, %d sudah tercatat, %d koneksi gagal.',
            $jumlah[AksiPelunasanSusulan::Dilunasi->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::AkanDilunasi->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::Dilaporkan->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::SudahTercatat->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::GagalKoneksi->value] ?? 0,
        ));
    }
}
