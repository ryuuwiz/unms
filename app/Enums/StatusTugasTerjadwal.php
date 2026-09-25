<?php

namespace App\Enums;

enum StatusTugasTerjadwal: string
{
    case Berhasil = 'berhasil';
    case Gagal = 'gagal';
    case Dilewati = 'dilewati';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Berhasil => 'Berhasil',
            self::Gagal => 'Gagal',
            self::Dilewati => 'Dilewati',
        };
    }

    /**
     * Mendapatkan warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Berhasil => 'green',
            self::Gagal => 'rose',
            self::Dilewati => 'zinc',
        };
    }
}
