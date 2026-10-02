<?php

namespace App\Console\Commands;

use App\Services\PaymentGateway\PemindaianPelunasanSusulan;
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

    public function handle(PemindaianPelunasanSusulan $pemindaian): int
    {
        $zonaWaktu = config('app.zona_waktu_bisnis');
        $sejak = $this->option('dari')
            ? Carbon::parse((string) $this->option('dari'), $zonaWaktu)->startOfDay()
            : now($zonaWaktu)->subDays(self::HARI_JENDELA)->startOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Memeriksa pembayaran PAID di Xendit sejak {$sejak->toDateString()}...");

        $hasil = $pemindaian->jalankanDenganKunci($sejak, $dryRun);
        if ($hasil === null) {
            $this->error('Pemindaian Pelunasan Susulan lain sedang berjalan. Coba lagi nanti.');

            return self::FAILURE;
        }

        $baris = $pemindaian->keBaris($hasil);
        if ($baris !== []) {
            $this->table(array_values(PemindaianPelunasanSusulan::KOLOM), array_map(
                fn (array $item): array => array_map(fn (string $kolom): string => (string) $item[$kolom], array_keys(PemindaianPelunasanSusulan::KOLOM)),
                $baris,
            ));
        }

        $this->info('Selesai. '.$pemindaian->ringkasan($hasil));

        return self::SUCCESS;
    }
}
