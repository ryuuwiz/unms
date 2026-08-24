<?php

namespace App\Observers;

use App\Jobs\Mikrotik\SyncBandwidthProfileToRoutersJob;
use App\Models\ProfilBandwidth;

class ProfilBandwidthObserver
{
    /**
     * Handle the ProfilBandwidth "saved" event.
     */
    public function saved(ProfilBandwidth $profil): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        SyncBandwidthProfileToRoutersJob::dispatch($profil);
    }
}
