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
     * Sinkronkan pool (dan Rantai IP Pool Router) ke router. Rename bebas (ADR-0060): pool bernama lama baru
     * dibersihkan setelah pool baru, rantai, dan profile menunjuk nama baru.
     *
     * Dispatch lewat SyncIpPoolToRouterJob::dispatch()->chain(...), bukan Bus::chain(...)->dispatch(): Bus::chain()
     * memanggil Dispatcher::dispatch() langsung dan tidak pernah mengecek ShouldBeUnique (hanya PendingDispatch yang
     * mengeceknya) -- setiap saved() akan mengantrikan job baru tanpa dedupe uniqueId()/uniqueFor(), bertentangan
     * dengan docblock job itu sendiri.
     */
    public function saved(IpPool $ipPool): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $router = $ipPool->router;

        if ($router->status_koneksi !== StatusRouter::Online) {
            return;
        }

        $chain = [];

        if ($ipPool->wasChanged('nama_pool') && ! $ipPool->wasChanged('router_id')) {
            $chain[] = new RemoveIpPoolFromRouterJob($ipPool->router_id, (string) $ipPool->getOriginal('nama_pool'), Auth::id() ?? 'system:tanpa-user', 'IP Pool di-rename');
        }

        SyncIpPoolToRouterJob::dispatch($ipPool)->chain($chain);
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
     * Pool dihapus: rantai & profile dialihkan, lalu pool, queue, dan profile lama `*@{pool}` dibersihkan di router.
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
