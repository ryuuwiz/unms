<?php

namespace App\Enums\Ticket;

enum SumberTicket: string
{
    case Manual = 'manual';
    case Sistem = 'sistem';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Sistem => 'Sistem Otomatis',
        };
    }
}
