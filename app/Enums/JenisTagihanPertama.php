<?php

namespace App\Enums;

enum JenisTagihanPertama: string
{
    case ProporsionalSisaHari = 'prorata';
    case SatuBulanFull = 'full_bulan';
    case Promo = 'promo';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProporsionalSisaHari => 'Tagih Proporsional Sisa Hari',
            self::SatuBulanFull => 'Tagih 1 Bulan Full',
            self::Promo => 'Gunakan Promo',
        };
    }
}
