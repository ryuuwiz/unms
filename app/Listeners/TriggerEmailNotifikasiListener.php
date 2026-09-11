<?php

namespace App\Listeners;

use App\Events\InvoicePaidEvent;
use App\Notifications\InvoicePaymentConfirmedNotification;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TriggerEmailNotifikasiListener implements ShouldQueue
{
    /**
     * Handle the event: kirim email konfirmasi pembayaran lunas.
     */
    public function handle(InvoicePaidEvent $event): void
    {
        $invoice = $event->invoice->loadMissing(['pelanggan', 'layananPelanggan.paketLayanan']);
        $pelanggan = $invoice->pelanggan;

        if ($pelanggan && ! empty($pelanggan->email)) {
            try {
                /** @var WhatsappService $whatsappService */
                $whatsappService = app(WhatsappService::class);
                $params = $whatsappService->buildPaymentParams($invoice, $event->pembayaran);

                $pelanggan->notify(new InvoicePaymentConfirmedNotification($invoice, $params));

                Log::info("Email konfirmasi pembayaran dikirim untuk invoice {$invoice->no_invoice} -> {$pelanggan->email}.");
            } catch (\Throwable $e) {
                Log::error("Gagal mengirim email konfirmasi pembayaran invoice {$invoice->no_invoice}: ".$e->getMessage());
            }
        }
    }
}
