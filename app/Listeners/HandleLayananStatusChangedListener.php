<?php

namespace App\Listeners;

use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Events\LayananPelangganStatusChangedEvent;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;

class HandleLayananStatusChangedListener
{
    /**
     * Tangani perubahan status layanan pelanggan untuk sinkronisasi ke router MikroTik.
     */
    public function handle(LayananPelangganStatusChangedEvent $event): void
    {
        $layanan = $event->layanan;

        if (! $layanan->router_id) {
            return;
        }

        if ($event->statusBaru === StatusLayanan::Suspend) {
            // Isolir PPPoE Secret di router dan putus sesi aktif
            DisablePppoeAccountJob::dispatch($layanan);
        } elseif ($event->statusBaru === StatusLayanan::Aktif) {
            // Jika belum pernah terprovisi sukses, jalankan provisi penuh
            if ($layanan->terprovisi_pada === null || $layanan->provisioning_status !== ProvisioningStatus::Success) {
                ProvisionPppoeAccountJob::dispatch($layanan);
            } else {
                // Jika sudah pernah terprovisi (misal un-suspend pasca pembayaran), aktifkan secret
                EnablePppoeAccountJob::dispatch($layanan);
            }
        } elseif ($event->statusBaru === StatusLayanan::Berhenti) {
            // Nonaktifkan secret saat berhenti berlangganan
            DisablePppoeAccountJob::dispatch($layanan);
        }
    }
}
