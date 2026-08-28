<?php

namespace App\Enums\Wa;

enum KategoriTemplateWa: string
{
    case Tagihan = 'tagihan';
    case Tiket = 'tiket';
    case Pembayaran = 'pembayaran';
    case Sistem = 'sistem';

    public function label(): string
    {
        return match ($this) {
            self::Tagihan => 'Tagihan & Invoice',
            self::Tiket => 'Tiket Gangguan / Layanan',
            self::Pembayaran => 'Konfirmasi Pembayaran',
            self::Sistem => 'Sistem & Operasional',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Tagihan => 'warning',
            self::Tiket => 'info',
            self::Pembayaran => 'success',
            self::Sistem => 'zinc',
        };
    }
}
