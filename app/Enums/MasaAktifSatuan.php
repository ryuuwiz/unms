<?php

namespace App\Enums;

enum MasaAktifSatuan: string
{
    case Hari = 'hari';
    case Bulan = 'bulan';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            MasaAktifSatuan::Hari => 'Hari',
            MasaAktifSatuan::Bulan => 'Bulan',
        };
    }
}
