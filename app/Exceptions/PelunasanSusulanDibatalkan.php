<?php

namespace App\Exceptions;

use App\Enums\AksiPelunasanSusulan;
use RuntimeException;

/**
 * Pelunasan Susulan dibatalkan di dalam transaksi DB (semua perubahan di-rollback): transaksi dari
 * koneksi sandbox (ADR-0067), atau pembayaran yang sama sudah diproses bersamaan oleh jalur lain.
 */
final class PelunasanSusulanDibatalkan extends RuntimeException
{
    public function __construct(
        public readonly AksiPelunasanSusulan $aksi,
        public readonly string $keterangan = '',
    ) {
        parent::__construct($keterangan);
    }
}
