<?php

namespace App\Enums\Sysblas;

enum SysblasProvider: string
{
    case Gowa = 'gowa';
    case Sms = 'sms';
    case Waha = 'waha';

    /**
     * Label tampilan nama provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gowa => 'GOWA Gateway',
            self::Sms => 'SMS Gateway',
            self::Waha => 'WAHA (WhatsApp HTTP API)',
        };
    }

    /**
     * Warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Gowa => 'blue',
            self::Sms => 'amber',
            self::Waha => 'green',
        };
    }
}
