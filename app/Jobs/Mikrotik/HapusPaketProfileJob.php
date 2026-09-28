<?php

namespace App\Jobs\Mikrotik;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Hapus PPP Profile paket dari router setelah Router Paket dihapus (profile yang masih dipakai secret dibiarkan).
 */
class HapusPaketProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180];

    public function __construct(public int $routerId, public string $namaProfile)
    {
        $this->onQueue('mikrotik-low');
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $router = Router::find($this->routerId);

        if ($router) {
            $mikrotikService->hapusPaketProfile($router, $this->namaProfile);
        }
    }
}
