<?php

namespace App\Jobs\PaymentGateway;

use App\Services\PaymentGateway\PemindaianPelunasanSusulan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Pemindaian Pelunasan Susulan dari halaman Pelunasan Susulan (pratinjau atau lunasi).
 *
 * Pemindaian riwayat panjang bisa melebihi `retry_after` koneksi antrean, sehingga worker lain
 * mengambil job ini lagi dan langsung gagal dengan MaxAttemptsExceededException. Kegagalan itu
 * tidak boleh melepas kunci: percobaan pertama masih berjalan dan melepasnya sendiri saat selesai.
 */
class PindaiPelunasanSusulanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public const BATAS_DETIK = 3300;

    public int $timeout = self::BATAS_DETIK;

    public function __construct(
        public string $idPemindaian,
        public string $sejak,
        public bool $dryRun,
        public string $pemilikKunci,
    ) {}

    public function handle(PemindaianPelunasanSusulan $pemindaian): void
    {
        $pemindaian->proses($this->idPemindaian, Carbon::parse($this->sejak), $this->dryRun, $this->pemilikKunci);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof MaxAttemptsExceededException) {
            return;
        }

        app(PemindaianPelunasanSusulan::class)->gagal($this->idPemindaian, $this->pemilikKunci, $exception?->getMessage() ?? 'Pemindaian gagal.');
    }
}
