<?php

namespace App\Observers;

use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\SyncIpPoolToRouterJob;
use App\Models\IpPool;

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

        if ($router && $router->status_koneksi === StatusRouter::Online) {
            SyncIpPoolToRouterJob::dispatch($ipPool);
        }
    }
}
