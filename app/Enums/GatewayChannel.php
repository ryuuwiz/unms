<?php

namespace App\Enums;

enum GatewayChannel: string
{
    case Invoice = 'invoice';
    case VirtualAccount = 'virtual_account';
    case Qris = 'qris';
    case Ewallet = 'ewallet';
    case RetailOutlet = 'retail_outlet';
    case Gopay = 'gopay';
    case Shopeepay = 'shopeepay';

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
            self::Gopay => 'GoPay',
            self::Shopeepay => 'ShopeePay',
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
            self::Gopay => 'teal',
            self::Shopeepay => 'orange',
        };
    }
}
