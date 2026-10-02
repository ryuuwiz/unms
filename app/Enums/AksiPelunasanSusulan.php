<?php

namespace App\Enums;

/**
 * Hasil penanganan satu pembayaran PAID gateway oleh Pelunasan Susulan -- lihat CONTEXT.md.
 */
enum AksiPelunasanSusulan: string
{
    case Dilunasi = 'DILUNASI';
    case AkanDilunasi = 'AKAN DILUNASI';
    case Dilaporkan = 'DILAPORKAN';
    case SudahTercatat = 'SUDAH TERCATAT';
    case GagalKoneksi = 'GAGAL KONEKSI';

    /**
     * Perlu ditindaklanjuti atau diberitahukan ke Admin (bukan pembayaran yang memang sudah tercatat).
     */
    public function perluDilaporkan(): bool
    {
        return $this !== self::SudahTercatat && $this !== self::AkanDilunasi;
    }
}
