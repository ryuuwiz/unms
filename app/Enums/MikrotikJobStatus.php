<?php

namespace App\Enums;

enum MikrotikJobStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Dilewati = 'dilewati';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Success => 'Berhasil',
            self::Failed => 'Gagal',
            self::Dilewati => 'Dilewati',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Success => 'green',
            self::Failed => 'red',
            self::Dilewati => 'zinc',
        };
    }
}
