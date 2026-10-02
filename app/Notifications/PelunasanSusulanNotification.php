<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Ringkasan satu eksekusi Pelunasan Susulan untuk Admin/super_admin -- lihat CONTEXT.md.
 */
class PelunasanSusulanNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $dilunasi,
        public int $perluTindakan,
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
            'title' => 'Pelunasan Susulan Xendit',
            'message' => "{$this->dilunasi} invoice dilunasi susulan dan {$this->perluTindakan} pembayaran perlu tindakan manual. Kasus yang perlu tindakan ada di halaman Pelunasan Susulan.",
            'url' => route('pembayaran.pelunasan-susulan.index'),
        ];
    }
}
