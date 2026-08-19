<?php

namespace App\Enums;

enum GatewayChannel: string
{
    case VirtualAccount = 'virtual_account';
    case Qris = 'qris';
    case Ewallet = 'ewallet';
    case RetailOutlet = 'retail_outlet';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::VirtualAccount => 'Virtual Account',
            self::Qris => 'QRIS',
            self::Ewallet => 'E-Wallet',
            self::RetailOutlet => 'Retail Outlet',
        };
    }

    /**
     * Mendapatkan warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::VirtualAccount => 'blue',
            self::Qris => 'emerald',
            self::Ewallet => 'purple',
            self::RetailOutlet => 'amber',
        };
    }
}
