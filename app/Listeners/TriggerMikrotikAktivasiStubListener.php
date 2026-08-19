<?php

namespace App\Listeners;

use App\Events\InvoicePaidEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TriggerMikrotikAktivasiStubListener implements ShouldQueue
{
    public string $queue = 'mikrotik';

    /**
     * Handle the event (Stub persiapan Fase 4: MikroTik Provisioning).
     */
    public function handle(InvoicePaidEvent $event): void
    {
        $layanan = $event->invoice->layananPelanggan;

        if ($layanan) {
            Log::info("Stub MikroTik: Layanan {$layanan->site_id} (PPP: {$layanan->ppp_username}) disiapkan untuk aktivasi/un-suspend.");
        }
    }
}
