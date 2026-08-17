<?php

namespace App\Enums;

enum JenisKoneksi: string
{
    case Pppoe = 'pppoe';
    case IpStatic = 'ip_static';

    /**
     * Mendapatkan label tampilan.
     */
    public function label(): string
    {
        return match ($this) {
            JenisKoneksi::Pppoe => 'PPPoE',
            JenisKoneksi::IpStatic => 'IP Static',
        };
    }
}
