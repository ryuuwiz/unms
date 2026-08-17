<?php

namespace App\Enums;

enum DiskonTipe: string
{
    case Persentase = 'persentase';
    case Nominal = 'nominal';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Persentase => 'Persentase (%)',
            self::Nominal => 'Nominal (Rp)',
        };
    }
}
