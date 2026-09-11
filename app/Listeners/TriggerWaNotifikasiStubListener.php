<?php

namespace App\Listeners;

use App\Events\InvoicePaidEvent;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TriggerWaNotifikasiStubListener implements ShouldQueue
{
    public string $queue = 'wa-blast';

    /**
     * Handle the event: Antrikan notifikasi WA kuitansi / konfirmasi pembayaran lunas.
     */
    public function handle(InvoicePaidEvent $event): void
    {
        $invoice = $event->invoice->loadMissing(['pelanggan', 'layananPelanggan.paketLayanan']);
        $pelanggan = $invoice->pelanggan;

        if ($pelanggan && ! empty($pelanggan->no_hp)) {
            try {
                /** @var WhatsappService $whatsappService */
                $whatsappService = app(WhatsappService::class);
                $params = $whatsappService->buildPaymentParams($invoice, $event->pembayaran);

                $whatsappService->antrikanPesan(
                    noHp: $pelanggan->no_hp,
                    kodeTemplate: 'pembayaran_konfirmasi',
                    params: $params,
                    referensi: $invoice,
                    jenis: 'pembayaran_konfirmasi'
                );

                Log::info("Notifikasi WA konfirmasi pembayaran diantrikan untuk invoice {$invoice->no_invoice} -> {$pelanggan->no_hp}.");
            } catch (\Throwable $e) {
                Log::error("Gagal mengantrikan WA konfirmasi pembayaran invoice {$invoice->no_invoice}: ".$e->getMessage());
            }
        }
    }
}
