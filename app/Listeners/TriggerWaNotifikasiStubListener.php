<?php

namespace App\Listeners;

use App\Events\InvoicePaidEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TriggerWaNotifikasiStubListener implements ShouldQueue
{
    public string $queue = 'wa-blast';

    /**
     * Handle the event (Stub persiapan Fase 5: Notifikasi WA Blast).
     */
    public function handle(InvoicePaidEvent $event): void
    {
        $pelanggan = $event->invoice->pelanggan;

        if ($pelanggan && $pelanggan->no_hp) {
            Log::info("Stub WA Blast: Notifikasi konfirmasi pembayaran invoice {$event->invoice->no_invoice} disiapkan untuk {$pelanggan->no_hp}.");
        }
    }
}
