<?php

namespace App\Enums;

enum StatusPelanggan: string
{
    case Aktif = 'aktif';
    case TidakAktif = 'tidak_aktif';
    case Prospek = 'prospek';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            StatusPelanggan::Aktif => 'Aktif',
            StatusPelanggan::TidakAktif => 'Tidak Aktif',
            StatusPelanggan::Prospek => 'Prospek',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            StatusPelanggan::Aktif => 'green',
            StatusPelanggan::TidakAktif => 'zinc',
            StatusPelanggan::Prospek => 'yellow',
        };
    }
}
