<?php

namespace App\Enums;

enum PriceMode: string
{
    case Paket = 'paket';
    case Custom = 'custom';

    /**
     * Mendapatkan label tampilan.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paket => 'Gunakan harga paket (auto)',
            self::Custom => 'Gunakan harga khusus (manual)',
        };
    }
}
