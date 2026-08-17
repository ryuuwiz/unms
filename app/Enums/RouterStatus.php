<?php

namespace App\Enums;

enum RouterStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Online => 'green',
            self::Offline => 'red',
            self::Unknown => 'zinc',
        };
    }
}
