<?php

namespace App\Enums;

/**
 * Keadaan sambungan sebuah layanan yang dilihat pelanggan di Status Koneksi (CONTEXT.md).
 */
enum KeadaanKoneksi: string
{
    case Online = 'online';
    case Offline = 'offline';
    case TidakTersedia = 'tidak_tersedia';
    case BelumTersambung = 'belum_tersambung';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::TidakTersedia => 'Status tidak tersedia',
            self::BelumTersambung => 'Belum tersambung',
        };
    }

    /**
     * Kelas warna teks Tailwind.
     */
    public function warna(): string
    {
        return match ($this) {
            self::Online => 'text-emerald-600 dark:text-emerald-400',
            self::Offline => 'text-rose-600 dark:text-rose-400',
            self::TidakTersedia, self::BelumTersambung => 'text-zinc-500 dark:text-zinc-400',
        };
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Online => 'signal',
            self::Offline => 'signal-slash',
            self::TidakTersedia => 'question-mark-circle',
            self::BelumTersambung => 'clock',
        };
    }

    /**
     * Router dapat ditanya ulang (layanan sudah punya router dan PPP Username).
     */
    public function bisaDicekUlang(): bool
    {
        return $this !== self::BelumTersambung;
    }
}
