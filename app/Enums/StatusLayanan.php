<?php

namespace App\Enums;

enum StatusLayanan: string
{
    case Proses = 'proses';
    case Aktif = 'aktif';
    case Suspend = 'suspend';
    case Berhenti = 'berhenti';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            StatusLayanan::Proses => 'Dalam Proses',
            StatusLayanan::Aktif => 'Aktif',
            StatusLayanan::Suspend => 'Suspend',
            StatusLayanan::Berhenti => 'Berhenti',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            StatusLayanan::Proses => 'yellow',
            StatusLayanan::Aktif => 'green',
            StatusLayanan::Suspend => 'orange',
            StatusLayanan::Berhenti => 'red',
        };
    }

    /**
     * Apakah layanan sedang dapat menggunakan internet.
     */
    public function isOnline(): bool
    {
        return $this === self::Aktif;
    }
}
