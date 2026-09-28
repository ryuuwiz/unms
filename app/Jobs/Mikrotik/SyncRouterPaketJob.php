<?php

namespace App\Jobs\Mikrotik;

use App\Models\RouterPaket;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Terapkan PPP Profile sebuah Router Paket ke router (ADR-0063); bila paket di-rename, profile lama
 * diganti namanya dulu agar secret yang merujuknya tidak kehilangan profile.
 */
class SyncRouterPaketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180];

    public function __construct(public RouterPaket $routerPaket, public ?string $namaProfileLama = null)
    {
        $this->onQueue('mikrotik-low');
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $router = $this->routerPaket->router;

        if ($this->namaProfileLama && $this->namaProfileLama !== $this->routerPaket->namaProfile()) {
            $mikrotikService->renamePaketProfile($router, $this->namaProfileLama, $this->routerPaket->namaProfile());
        }

        $mikrotikService->ensurePaketProfile($this->routerPaket);
    }
}
