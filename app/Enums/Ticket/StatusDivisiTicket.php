<?php

namespace App\Enums\Ticket;

enum StatusDivisiTicket: string
{
    case Belum = 'belum';
    case Progress = 'progress';
    case Selesai = 'selesai';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum',
            self::Progress => 'Progress',
            self::Selesai => 'Selesai',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Belum => 'zinc',
            self::Progress => 'amber',
            self::Selesai => 'emerald',
        };
    }
}
