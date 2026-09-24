<?php

namespace App\Observers;

use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\RemoveIpPoolFromRouterJob;
use App\Jobs\Mikrotik\SyncIpPoolToRouterJob;
use App\Models\IpPool;
use App\Models\Router;
use Illuminate\Support\Facades\Auth;

class IpPoolObserver
{
    /**
     * Handle the IpPool "saved" event.
     */
    public function saved(IpPool $ipPool): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $router = $ipPool->router;

        if ($router->status_koneksi === StatusRouter::Online) {
            SyncIpPoolToRouterJob::dispatch($ipPool);
        }
    }

    /**
     * Pool dipindah ke router lain: bersihkan jejaknya di router lama.
     */
    public function updated(IpPool $ipPool): void
    {
        if ($ipPool->wasChanged('router_id')) {
            $this->bersihkanDiRouter((int) $ipPool->getOriginal('router_id'), (string) $ipPool->getOriginal('nama_pool'), 'IP Pool dipindah ke router lain');
        }
    }

    /**
     * Pool dihapus (hanya mungkin bila tidak dipakai layanan): bersihkan pool, queue, dan profile per pool di router.
     */
    public function deleted(IpPool $ipPool): void
    {
        $this->bersihkanDiRouter($ipPool->router_id, $ipPool->nama_pool, 'IP Pool dihapus dari UNMS');
    }

    private function bersihkanDiRouter(int $routerId, string $namaPool, string $alasan): void
    {
        if (Router::whereKey($routerId)->value('status_koneksi') !== StatusRouter::Online) {
            return;
        }

        RemoveIpPoolFromRouterJob::dispatch($routerId, $namaPool, Auth::id() ?? 'system:tanpa-user', $alasan);
    }
}
