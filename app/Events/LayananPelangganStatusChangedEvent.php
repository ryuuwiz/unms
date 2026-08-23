<?php

namespace App\Events;

use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LayananPelangganStatusChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LayananPelanggan $layanan,
        public readonly StatusLayanan $statusLama,
        public readonly StatusLayanan $statusBaru,
        public readonly ?User $actor = null,
        public readonly ?string $catatan = null
    ) {}
}
