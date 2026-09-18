<?php

namespace App\Enums;

enum StatusInvoice: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case Lunas = 'lunas';
    case Kadaluarsa = 'kadaluarsa';
    case Dibatalkan = 'dibatalkan';
    case Digabung = 'digabung';

    /**
     * Status invoice yang masih berupa kewajiban bayar (belum lunas dan belum gugur).
     *
     * @return array<int, self>
     */
    public static function terbuka(): array
    {
        return [self::MenungguPembayaran, self::Kadaluarsa];
    }

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
            self::Digabung => 'Digabung',
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
            self::Digabung => 'sky',
        };
    }
}
