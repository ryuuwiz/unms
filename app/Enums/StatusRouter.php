<?php

namespace App\Enums;

enum StatusRouter: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Unknown = 'unknown';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            StatusRouter::Online => 'Online',
            StatusRouter::Offline => 'Offline',
            StatusRouter::Unknown => 'Tidak Diketahui',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            StatusRouter::Online => 'green',
            StatusRouter::Offline => 'red',
            StatusRouter::Unknown => 'zinc',
        };
    }
}
