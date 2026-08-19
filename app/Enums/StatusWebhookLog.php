<?php

namespace App\Enums;

enum StatusWebhookLog: string
{
    case Diterima = 'diterima';
    case Diproses = 'diproses';
    case Gagal = 'gagal';
    case Diabaikan = 'diabaikan';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Diterima => 'Diterima',
            self::Diproses => 'Diproses',
            self::Gagal => 'Gagal',
            self::Diabaikan => 'Diabaikan',
        };
    }

    /**
     * Mendapatkan warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Diterima => 'cyan',
            self::Diproses => 'green',
            self::Gagal => 'rose',
            self::Diabaikan => 'zinc',
        };
    }
}
