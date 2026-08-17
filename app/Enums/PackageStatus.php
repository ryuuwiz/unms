<?php

namespace App\Enums;

enum PackageStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the Indonesian display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            PackageStatus::Active => 'Aktif',
            PackageStatus::Inactive => 'Nonaktif',
        };
    }

    /**
     * Get the badge color for Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            PackageStatus::Active => 'green',
            PackageStatus::Inactive => 'zinc',
        };
    }

    /**
     * Check if status is active.
     */
    public function isActive(): bool
    {
        return $this === PackageStatus::Active;
    }
}
