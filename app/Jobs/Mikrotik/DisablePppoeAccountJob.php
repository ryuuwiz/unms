<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\User;
use App\Notifications\MikrotikJobFailedNotification;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class DisablePppoeAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public LayananPelanggan $layanan,
        public bool $disconnectActive = true
    ) {
        $this->onQueue('mikrotik');
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $router = $this->layanan->router;

        if (! $router) {
            return;
        }

        $log = MikrotikJobLog::create([
            'router_id' => $router->id,
            'layanan_pelanggan_id' => $this->layanan->id,
            'job_type' => MikrotikJobType::DisablePppoe,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => $this->attempts(),
            'payload' => [
                'username' => $this->layanan->ppp_username,
                'disconnect_active' => $this->disconnectActive,
            ],
        ]);

        try {
            $mikrotikService->disablePppoeSecret($router, $this->layanan, $this->disconnectActive);

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $router = $this->layanan->router;
        if (! $router) {
            return;
        }

        $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
            ->where('job_type', MikrotikJobType::DisablePppoe)
            ->latest()
            ->first();

        if ($log) {
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $exception?->getMessage() ?? 'Gagal menonaktifkan akun PPPoE setelah 3 percobaan',
                'finished_at' => Carbon::now(),
            ]);

            $recipients = User::role(['super_admin', 'noc'])->get();
            foreach ($recipients as $recipient) {
                $recipient->notify(new MikrotikJobFailedNotification($log));
            }
        }
    }
}
