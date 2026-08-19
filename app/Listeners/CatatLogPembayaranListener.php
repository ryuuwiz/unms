<?php

namespace App\Listeners;

use App\Events\InvoicePaidEvent;
use Illuminate\Support\Facades\Log;

class CatatLogPembayaranListener
{
    /**
     * Handle the event.
     */
    public function handle(InvoicePaidEvent $event): void
    {
        $invoice = $event->invoice;
        $pembayaran = $event->pembayaran;

        Log::info("Pembayaran invoice {$invoice->no_invoice} berhasil dicatat via {$pembayaran->metode->value}", [
            'invoice_id' => $invoice->id,
            'pembayaran_id' => $pembayaran->id,
            'jumlah' => $pembayaran->jumlah_dibayar,
            'metode' => $pembayaran->metode->value,
            'referensi' => $pembayaran->referensi_transaksi,
        ]);
    }
}
