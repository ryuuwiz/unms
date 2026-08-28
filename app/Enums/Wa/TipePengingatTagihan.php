<?php

namespace App\Enums\Wa;

enum TipePengingatTagihan: string
{
    case SebelumJatuhTempo = 'sebelum_jatuh_tempo';
    case HariH = 'hari_h';
    case SetelahJatuhTempo = 'setelah_jatuh_tempo';

    public function label(): string
    {
        return match ($this) {
            self::SebelumJatuhTempo => 'Sebelum Jatuh Tempo (H-X)',
            self::HariH => 'Hari-H Jatuh Tempo (H-0)',
            self::SetelahJatuhTempo => 'Setelah Jatuh Tempo / Tunggakan (H+X)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SebelumJatuhTempo => 'info',
            self::HariH => 'warning',
            self::SetelahJatuhTempo => 'danger',
        };
    }
}
