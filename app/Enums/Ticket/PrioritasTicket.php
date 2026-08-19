<?php

namespace App\Enums\Ticket;

enum PrioritasTicket: string
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';
    case Darurat = 'darurat';

    public function label(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Sedang => 'Sedang',
            self::Tinggi => 'Tinggi',
            self::Darurat => 'Darurat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Rendah => 'zinc',
            self::Sedang => 'sky',
            self::Tinggi => 'amber',
            self::Darurat => 'rose',
        };
    }

    public function durasiSlaHours(): int
    {
        return match ($this) {
            self::Darurat => 4,
            self::Tinggi => 24,
            self::Sedang => 72,
            self::Rendah => 168,
        };
    }

    public function slaLabel(): string
    {
        return match ($this) {
            self::Darurat => '4 Jam',
            self::Tinggi => '24 Jam (1 Hari)',
            self::Sedang => '3 Hari',
            self::Rendah => '7 Hari',
        };
    }
}
