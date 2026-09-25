<?php

namespace App\Notifications;

use App\Enums\MikrotikJobStatus;
use App\Models\MikrotikJobLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi NOC atas hasil job MikroTik (sukses/gagal) -- tampil di lonceng aplikasi.
 */
class MikrotikJobNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MikrotikJobLog $jobLog
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function judul(): string
    {
        $jobLabel = $this->jobLog->job_type->label();

        return match ($this->jobLog->status) {
            MikrotikJobStatus::Success => "MikroTik Berhasil: {$jobLabel}",
            default => "MikroTik Gagal: {$jobLabel}",
        };
    }

    public function pesan(): string
    {
        $router = $this->jobLog->router->nama_router;
        $username = $this->jobLog->payload['username'] ?? null;
        $subjek = $username ? "{$username} di router {$router}" : "router {$router}";

        return $this->jobLog->payload['pesan'] ?? ($this->jobLog->status === MikrotikJobStatus::Success
            ? "Berhasil untuk {$subjek}."
            : "Gagal untuk {$subjek} setelah {$this->jobLog->attempt_count} percobaan. Error: {$this->jobLog->error_message}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $pelangganId = $this->jobLog->layananPelanggan?->pelanggan_id;

        return [
            'job_log_id' => $this->jobLog->id,
            'router_id' => $this->jobLog->router_id,
            'job_type' => $this->jobLog->job_type->value,
            'status' => $this->jobLog->status->value,
            'title' => $this->judul(),
            'message' => $this->pesan(),
            'url' => $pelangganId ? route('pelanggan.show', $pelangganId) : route('router.edit', $this->jobLog->router_id),
        ];
    }
}
