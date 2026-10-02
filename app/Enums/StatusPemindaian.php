<?php

namespace App\Enums;

/**
 * Status satu pemindaian Pelunasan Susulan dari halaman Pelunasan Susulan.
 */
enum StatusPemindaian: string
{
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';
    case Gagal = 'gagal';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Berjalan => 'Sedang memeriksa…',
            self::Selesai => 'Selesai',
            self::Gagal => 'Gagal',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Berjalan => 'blue',
            self::Selesai => 'green',
            self::Gagal => 'red',
            self::Dibatalkan => 'zinc',
        };
    }
}
