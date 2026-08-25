<?php

namespace App\Enums\Ticket;

enum SumberTicket: string
{
    case Manual = 'manual';
    case Sistem = 'sistem';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Sistem => 'Sistem Otomatis',
            self::Portal => 'Portal Pelanggan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'zinc',
            self::Sistem => 'amber',
            self::Portal => 'violet',
        };
    }
}
