<?php

namespace App\Console\Commands;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\Enums\AksiPelunasanSusulan;
use App\Services\PaymentGateway\PelunasanSusulan;
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
                            {--dari= : Tanggal awal waktu bayar di Xendit (Y-m-d), default 35 hari lalu}
                            {--dry-run : Tampilkan yang akan dilunasi tanpa mengubah data}';

    /**
     * @var string
     */
    protected $description = 'Pelunasan Susulan: lunasi invoice yang sudah PAID di Xendit tetapi belum Lunas di sistem (ADR-0069)';

    public function handle(PelunasanSusulan $pelunasanSusulan): int
    {
        $sejak = $this->option('dari')
            ? Carbon::parse((string) $this->option('dari'))->startOfDay()
            : now()->subDays(self::HARI_JENDELA)->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Memeriksa pembayaran PAID di Xendit sejak {$sejak->toDateString()}...");

        $daftarHasil = $pelunasanSusulan->jalankan($sejak, $dryRun);
        if (! $dryRun) {
            $pelunasanSusulan->laporkan($daftarHasil);
        }
        $hasil = collect($daftarHasil);
        $ditampilkan = $hasil->reject(fn (HasilPelunasanSusulan $item): bool => $item->aksi === AksiPelunasanSusulan::SudahTercatat);

        if ($ditampilkan->isNotEmpty()) {
            $this->table(
                ['Koneksi', 'No Invoice', 'Pelanggan', 'Nominal', 'Dibayar', 'Status Lokal', 'Aksi', 'Keterangan'],
                $ditampilkan->map(fn (HasilPelunasanSusulan $item): array => [
                    $item->koneksi,
                    $item->invoice?->no_invoice ?? $item->pembayaran?->externalId ?? '-',
                    $item->invoice?->pelanggan?->namaLengkap() ?? '-',
                    $item->pembayaran ? 'Rp '.number_format($item->pembayaran->paidAmount, 0, ',', '.') : '-',
                    $item->pembayaran?->paidAt ?? '-',
                    $item->statusSebelum ?? '-',
                    $item->aksi->value,
                    $item->keterangan,
                ])->all(),
            );
        }

        $hitung = fn (AksiPelunasanSusulan $aksi): int => $hasil->filter(fn (HasilPelunasanSusulan $item): bool => $item->aksi === $aksi)->count();
        $this->info(sprintf(
            'Selesai. %d dilunasi, %d akan dilunasi (dry run), %d dilaporkan, %d sudah tercatat, %d koneksi gagal.',
            $hitung(AksiPelunasanSusulan::Dilunasi),
            $hitung(AksiPelunasanSusulan::AkanDilunasi),
            $hitung(AksiPelunasanSusulan::Dilaporkan),
            $hitung(AksiPelunasanSusulan::SudahTercatat),
            $hitung(AksiPelunasanSusulan::GagalKoneksi),
        ));

        return self::SUCCESS;
    }
}
