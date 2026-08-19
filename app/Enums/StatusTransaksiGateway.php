<?php

namespace App\Enums;

enum StatusTransaksiGateway: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Lunas / Berhasil',
            self::Expired => 'Kedaluwarsa',
            self::Failed => 'Gagal',
        };
    }

    /**
     * Mendapatkan warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Paid => 'green',
            self::Expired => 'rose',
            self::Failed => 'zinc',
        };
    }
}
