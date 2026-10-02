<?php

namespace App\Jobs\PaymentGateway;

use App\Services\PaymentGateway\PemindaianPelunasanSusulan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Pemindaian Pelunasan Susulan dari halaman Pelunasan Susulan (pratinjau atau lunasi).
 */
class PindaiPelunasanSusulanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3300;

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
        app(PemindaianPelunasanSusulan::class)->gagal($this->idPemindaian, $this->pemilikKunci, $exception?->getMessage() ?? 'Pemindaian gagal.');
    }
}
