<?php

namespace App\Enums\Wa;

enum StatusAntrianWa: string
{
    case Menunggu = 'menunggu';
    case Diproses = 'diproses';
    case Terkirim = 'terkirim';
    case Gagal = 'gagal';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Antrean',
            self::Diproses => 'Sedang Diproses',
            self::Terkirim => 'Terkirim',
            self::Gagal => 'Gagal Dikirim',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Menunggu => 'zinc',
            self::Diproses => 'warning',
            self::Terkirim => 'success',
            self::Gagal => 'danger',
        };
    }
}
