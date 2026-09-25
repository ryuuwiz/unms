<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikJobLog;
use App\Models\User;
use App\Notifications\MikrotikJobNotification;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Notifikasi NOC -- lihat CONTEXT.md "Notifikasi NOC". Lonceng untuk user noc & super_admin;
 * WhatsApp hanya untuk kejadian genting (gagal provisi, router offline/online).
 */
class NotifikasiNoc
{
    public function __construct(private WhatsappService $whatsapp) {}

    public function kirim(MikrotikJobLog $log, bool $whatsapp = false): void
    {
        $penerima = User::role(['super_admin', 'noc'])->get();
        $notifikasi = new MikrotikJobNotification($log);

        Notification::send($penerima, $notifikasi);

        if (! $whatsapp) {
            return;
        }

        $pesan = "*{$notifikasi->judul()}*\n{$notifikasi->pesan()}";

        foreach ($penerima->filter(fn (User $user) => filled($user->phone)) as $user) {
            try {
                // jenis per user: antrean WA unik per (referensi, jenis, tanggal).
                $this->whatsapp->antrikanPesanKustom((string) $user->phone, $pesan, referensi: $log, jenis: "noc_mikrotik_u{$user->id}");
            } catch (Throwable $e) {
                // Gateway WA bermasalah tidak boleh menggagalkan job MikroTik; lonceng sudah terkirim.
                report($e);
            }
        }
    }
}
