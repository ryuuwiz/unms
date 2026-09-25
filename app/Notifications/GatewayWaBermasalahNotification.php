<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Gateway WhatsApp menolak/tidak terjangkau (401/403, timeout, 5xx) -- tampil di lonceng admin.
 * Gagal per nomor tidak diberitahukan; cukup terlihat di halaman Antrian WA.
 */
class GatewayWaBermasalahNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $namaGateway,
        public string $galat,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status' => 'failed',
            'title' => "Gateway WhatsApp Bermasalah: {$this->namaGateway}",
            'message' => "Pesan WhatsApp ke pelanggan tidak terkirim. Error: {$this->galat}. Periksa koneksi gateway.",
            'url' => route('sysblas.koneksi.index'),
        ];
    }
}
