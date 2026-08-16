<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the Indonesian display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            CustomerStatus::Active => 'Aktif',
            CustomerStatus::Inactive => 'Nonaktif',
        };
    }

    /**
     * Get the badge color for Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            CustomerStatus::Active => 'green',
            CustomerStatus::Inactive => 'zinc',
        };
    }
}
