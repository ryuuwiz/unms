<?php

namespace App\Enums;

enum StatusInvoice: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case Lunas = 'lunas';
    case Kadaluarsa = 'kadaluarsa';
    case Dibatalkan = 'dibatalkan';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::Lunas => 'Lunas',
            self::Kadaluarsa => 'Kadaluarsa',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Mendapatkan warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'amber',
            self::Lunas => 'green',
            self::Kadaluarsa => 'rose',
            self::Dibatalkan => 'zinc',
        };
    }
}
