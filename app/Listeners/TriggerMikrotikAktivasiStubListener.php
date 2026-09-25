<?php

namespace App\Listeners;

use App\Enums\ProvisioningStatus;
use App\Events\InvoicePaidEvent;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;

/**
 * Tidak di-queue: cukup mengantrekan job MikroTik, sehingga un-isolir pasca bayar hanya satu lompatan
 * antrean. afterCommit: job membaca status layanan yang sudah tersimpan oleh transaksi pembayaran.
 */
class TriggerMikrotikAktivasiStubListener
{
    /**
     * Handle event pembayaran invoice untuk aktivasi / un-suspend otomatis di MikroTik.
     */
    public function handle(InvoicePaidEvent $event): void
    {
        $layanan = $event->invoice->layananPelanggan;

        if (! $layanan || ! $layanan->router_id) {
            return;
        }

        // Jika layanan belum pernah terprovisi, lakukan provisioning penuh
        if ($layanan->terprovisi_pada === null || $layanan->provisioning_status !== ProvisioningStatus::Success) {
            ProvisionPppoeAccountJob::dispatch($layanan)->afterCommit();
        } else {
            // Jika sudah pernah terprovisi, aktifkan kembali akun PPPoE
            EnablePppoeAccountJob::dispatch($layanan)->afterCommit();
        }
    }
}
