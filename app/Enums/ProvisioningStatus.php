<?php

namespace App\Enums;

enum ProvisioningStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Provisi',
            self::Success => 'Terprovisi',
            self::Failed => 'Gagal Provisi',
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
        };
    }
}
