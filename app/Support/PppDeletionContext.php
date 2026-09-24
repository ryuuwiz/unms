<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Jejak input untuk setiap penghapusan PPP Secret di router: siapa (user ID atau `system:<sumber>`)
 * dan alasannya. Service menolak penghapusan tanpa konteks ini (CONTEXT.md "Penghapusan PPP Secret").
 */
final readonly class PppDeletionContext
{
    public function __construct(
        public int|string $actor,
        public string $reason,
    ) {
        if (trim((string) $actor) === '' || trim($reason) === '') {
            throw new InvalidArgumentException('Penghapusan PPP Secret wajib menyertakan actor dan alasan.');
        }
    }

    /**
     * Konteks untuk aksi user yang login; tanpa user (console/queue tanpa auth) dicatat sebagai sumber sistem.
     */
    public static function forUser(?int $userId, string $reason, string $fallbackSource = 'system:tanpa-user'): self
    {
        return new self($userId ?? $fallbackSource, $reason);
    }

    public static function system(string $source, string $reason): self
    {
        return new self("system:{$source}", $reason);
    }

    /**
     * @return array{actor: int|string, reason: string}
     */
    public function toArray(): array
    {
        return ['actor' => $this->actor, 'reason' => $this->reason];
    }
}
