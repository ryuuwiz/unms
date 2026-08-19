<?php

namespace App\Enums\Ticket;

enum StatusTicket: string
{
    case Baru = 'baru';
    case Diproses = 'diproses';
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Diproses => 'Diproses',
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Baru => 'sky',
            self::Diproses => 'amber',
            self::MenungguKonfirmasi => 'indigo',
            self::Selesai => 'emerald',
            self::Batal => 'rose',
        };
    }

    /**
     * @return array<int, StatusTicket>
     */
    public function transisiValid(): array
    {
        return match ($this) {
            self::Baru => [self::Diproses, self::Batal],
            self::Diproses => [self::MenungguKonfirmasi, self::Batal],
            self::MenungguKonfirmasi => [self::Selesai, self::Diproses],
            self::Selesai => [],
            self::Batal => [],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Selesai, self::Batal], true);
    }
}
