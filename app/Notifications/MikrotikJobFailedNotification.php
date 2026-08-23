<?php

namespace App\Notifications;

use App\Models\MikrotikJobLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MikrotikJobFailedNotification extends Notification
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $routerName = $this->jobLog->router?->nama_router ?? 'Router';
        $jobLabel = $this->jobLog->job_type->label();

        return [
            'job_log_id' => $this->jobLog->id,
            'router_id' => $this->jobLog->router_id,
            'job_type' => $this->jobLog->job_type->value,
            'title' => "Integrasi MikroTik Gagal: {$jobLabel}",
            'message' => "Job {$jobLabel} pada router {$routerName} gagal setelah {$this->jobLog->attempt_count} percobaan. Error: {$this->jobLog->error_message}",
            'url' => route('mikrotik.logs.index', ['router_id' => $this->jobLog->router_id]),
        ];
    }
}
