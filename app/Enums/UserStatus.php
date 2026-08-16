<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            UserStatus::Active => 'Active',
            UserStatus::Inactive => 'Inactive',
        };
    }
}
