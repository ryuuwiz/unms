<?php

namespace App\Enums;

enum TipePelanggan: string
{
    case Rumah = 'rumah';
    case Bisnis = 'bisnis';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            TipePelanggan::Rumah => 'Rumah',
            TipePelanggan::Bisnis => 'Bisnis',
        };
    }

    /**
     * Mendapatkan ikon Flux.
     */
    public function icon(): string
    {
        return match ($this) {
            TipePelanggan::Rumah => 'home',
            TipePelanggan::Bisnis => 'building-office',
        };
    }
}
