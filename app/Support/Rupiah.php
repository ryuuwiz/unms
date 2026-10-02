<?php

namespace App\Support;

/**
 * Format nominal rupiah untuk teks yang dibaca orang (laporan, keterangan): "Rp 150.000".
 */
final class Rupiah
{
    public static function format(float|int $nominal): string
    {
        return 'Rp '.number_format((float) $nominal, 0, ',', '.');
    }
}
