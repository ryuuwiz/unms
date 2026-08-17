<?php

namespace App\Enums;

enum JenisPromo: string
{
    case Diskon = 'diskon';
    case BonusDurasi = 'bonus_durasi';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Diskon => 'Diskon Potongan Harga',
            self::BonusDurasi => 'Bonus Perpanjangan Durasi',
        };
    }

    /**
     * Mendapatkan warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Diskon => 'indigo',
            self::BonusDurasi => 'cyan',
        };
    }
}
