<?php

namespace App\Enums;

enum MetodePembayaran: string
{
    case ManualAdmin = 'manual_admin';
    case Transfer = 'transfer';
    case PaymentGateway = 'payment_gateway';

    /**
     * Mendapatkan label tampilan Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManualAdmin => 'Manual (Admin/Kasir)',
            self::Transfer => 'Transfer Bank Langsung',
            self::PaymentGateway => 'Payment Gateway (Otomatis)',
        };
    }

    /**
     * Mendapatkan warna badge untuk Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::ManualAdmin => 'blue',
            self::Transfer => 'purple',
            self::PaymentGateway => 'emerald',
        };
    }
}
