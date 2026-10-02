<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pemantauan Sesi PPP melihat sesi sebuah layanan berubah (ADR-0070). Hanya membawa id layanan:
 * halaman Detail Pelanggan yang menerimanya mengambil status lengkap sendiri dari router.
 */
class StatusSesiPppBerubah implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly int $pelangganId, public readonly int $layananId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("pelanggan.{$this->pelangganId}");
    }

    /**
     * @return array{layanan_id: int}
     */
    public function broadcastWith(): array
    {
        return ['layanan_id' => $this->layananId];
    }
}
