<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceTerbitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $params  Parameter tampilan invoice, lihat WhatsappService::buildInvoiceParams().
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
            ->subject("Tagihan Baru {$this->params['no_invoice']}")
            ->greeting("Halo, {$this->params['nama_pelanggan']}")
            ->line("Tagihan {$this->params['nama_paket']} Anda sebesar {$this->params['total_tagihan']} telah terbit dan jatuh tempo pada {$this->params['jatuh_tempo']}.")
            ->action('Lihat & Bayar Tagihan', $this->params['link_pembayaran'])
            ->line("Abaikan email ini jika Anda sudah melakukan pembayaran. Terima kasih telah menggunakan layanan {$this->params['nama_perusahaan']}.");
    }
}
