<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusRouter;
use App\Models\MikrotikJobLog;
use App\Models\ProfilBandwidth;
use App\Models\Router;
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

class SyncBandwidthProfileToRoutersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public ProfilBandwidth $profil
    ) {
        $this->onQueue('mikrotik');
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $routers = Router::where('status_koneksi', StatusRouter::Online)->get();

        if ($routers->isEmpty()) {
            return;
        }

        foreach ($routers as $router) {
            $log = MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::SyncProfilBandwidth,
                'status' => MikrotikJobStatus::Pending,
                'attempt_count' => $this->attempts(),
                'payload' => [
                    'profil_bandwidth_id' => $this->profil->id,
                    'nama_bandwidth' => $this->profil->nama_bandwidth,
                    'rate_limit' => $this->profil->routerOsRateLimit(),
                ],
            ]);

            try {
                $mikrotikService->ensurePppProfile($router, $this->profil);

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
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = MikrotikJobLog::where('job_type', MikrotikJobType::SyncProfilBandwidth)
            ->latest()
            ->first();

        if ($log) {
            $recipients = User::role(['super_admin', 'noc'])->get();
            foreach ($recipients as $recipient) {
                $recipient->notify(new MikrotikJobFailedNotification($log));
            }
        }
    }
}
