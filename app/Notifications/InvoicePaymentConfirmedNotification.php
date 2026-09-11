<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $params  Parameter tampilan invoice + pembayaran, lihat WhatsappService::buildPaymentParams().
     */
    public function __construct(
        public Invoice $invoice,
        public array $params,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Konfirmasi Pembayaran Tagihan {$this->params['no_invoice']}")
            ->greeting("Halo, {$this->params['nama_pelanggan']}")
            ->line("Pembayaran tagihan {$this->params['no_invoice']} sebesar {$this->params['jumlah_dibayar']} pada {$this->params['tanggal_bayar']} via {$this->params['metode_bayar']} telah kami terima. Terima kasih!")
            ->action('Lihat Rincian Tagihan', $this->params['link_pembayaran'])
            ->line("Salam, {$this->params['nama_perusahaan']}.");
    }
}
