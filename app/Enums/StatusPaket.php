<?php

namespace App\Enums;

enum StatusPaket: string
{
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            StatusPaket::Aktif => 'Aktif',
            StatusPaket::Nonaktif => 'Nonaktif',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            StatusPaket::Aktif => 'green',
            StatusPaket::Nonaktif => 'zinc',
        };
    }
}
