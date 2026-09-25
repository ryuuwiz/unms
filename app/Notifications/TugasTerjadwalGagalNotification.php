<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TugasTerjadwalGagalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $perintah
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
            'title' => 'Tugas Terjadwal Gagal',
            'message' => "Tugas terjadwal `{$this->perintah}` gagal. Lihat detail di Log Tugas Terjadwal.",
            'url' => route('tugas-terjadwal.index', ['perintah' => $this->perintah, 'status' => 'gagal']),
        ];
    }
}
