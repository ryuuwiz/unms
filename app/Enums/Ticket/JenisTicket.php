<?php

namespace App\Enums\Ticket;

enum JenisTicket: string
{
    case Pemasangan = 'pemasangan';
    case Pencabutan = 'pencabutan';
    case Gangguan = 'gangguan';
    case PindahAlamat = 'pindah_alamat';

    public function label(): string
    {
        return match ($this) {
            self::Pemasangan => 'Pemasangan',
            self::Pencabutan => 'Pencabutan',
            self::Gangguan => 'Gangguan',
            self::PindahAlamat => 'Pindah Alamat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pemasangan => 'emerald',
            self::Pencabutan => 'zinc',
            self::Gangguan => 'rose',
            self::PindahAlamat => 'amber',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pemasangan => 'wrench-screwdriver',
            self::Pencabutan => 'archive-box-x-mark',
            self::Gangguan => 'exclamation-triangle',
            self::PindahAlamat => 'arrow-path',
        };
    }
}
