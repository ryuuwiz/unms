<?php

namespace App\Enums\Ticket;

enum DivisiTicket: string
{
    case Admin = 'admin';
    case CustomerService = 'customer_service';
    case Sales = 'sales';
    case Noc = 'noc';
    case Teknisi = 'teknisi';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::CustomerService => 'Customer Service',
            self::Sales => 'Sales',
            self::Noc => 'NOC',
            self::Teknisi => 'Teknisi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'zinc',
            self::CustomerService => 'indigo',
            self::Sales => 'amber',
            self::Noc => 'cyan',
            self::Teknisi => 'emerald',
        };
    }
}
