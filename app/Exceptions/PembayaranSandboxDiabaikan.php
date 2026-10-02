<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelunasan Susulan dibatalkan karena transaksi berasal dari koneksi sandbox (ADR-0067); dipakai
 * untuk me-rollback pelepasan dari invoice penggabung dalam transaksi DB yang sama.
 */
final class PembayaranSandboxDiabaikan extends RuntimeException {}
