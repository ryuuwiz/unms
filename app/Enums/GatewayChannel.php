<?php

namespace App\Enums;

enum GatewayChannel: string
{
    case Invoice = 'invoice';
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
            self::Invoice => 'Xendit Invoice',
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
            self::Invoice => 'indigo',
            self::VirtualAccount => 'blue',
            self::Qris => 'emerald',
            self::Ewallet => 'purple',
            self::RetailOutlet => 'amber',
        };
    }
}
