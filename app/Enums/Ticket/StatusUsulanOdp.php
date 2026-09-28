<?php

namespace App\Enums\Ticket;

/**
 * Status Usulan ODP pada Ticket Pemasangan -- lihat CONTEXT.md "Usulan ODP".
 */
enum StatusUsulanOdp: string
{
    case Menunggu = 'menunggu_validasi';
    case Disetujui = 'disetujui';
    case Diganti = 'diganti';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Validasi Teknisi',
            self::Disetujui => 'Disetujui',
            self::Diganti => 'Diganti Teknisi',
        };
    }

    /**
     * Mendapatkan warna badge Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Menunggu => 'amber',
            self::Disetujui => 'emerald',
            self::Diganti => 'sky',
        };
    }
}
