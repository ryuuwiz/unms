<?php

namespace App\Enums;

enum StatusOdpPort: string
{
    case Kosong = 'kosong';
    case Terpakai = 'terpakai';
    case Rusak = 'rusak';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            StatusOdpPort::Kosong => 'Kosong',
            StatusOdpPort::Terpakai => 'Terpakai',
            StatusOdpPort::Rusak => 'Rusak',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            StatusOdpPort::Kosong => 'green',
            StatusOdpPort::Terpakai => 'blue',
            StatusOdpPort::Rusak => 'red',
        };
    }
}
