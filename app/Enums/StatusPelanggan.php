<?php

namespace App\Enums;

enum StatusPelanggan: string
{
    case BelumTerpasang = 'belum_terpasang';
    case ReqPemasangan = 'req_pemasangan';
    case PemasanganSelesai = 'pemasangan_selesai';
    case Aktif = 'aktif';
    case Expired = 'expired';
    case Off = 'off';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::BelumTerpasang => 'Belum Terpasang',
            self::ReqPemasangan => 'Req. Pemasangan',
            self::PemasanganSelesai => 'Pemasangan Selesai',
            self::Aktif => 'Aktif',
            self::Expired => 'Expired',
            self::Off => 'Off',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::BelumTerpasang => 'zinc',
            self::ReqPemasangan => 'cyan',
            self::PemasanganSelesai => 'indigo',
            self::Aktif => 'emerald',
            self::Expired => 'amber',
            self::Off => 'rose',
        };
    }
}
