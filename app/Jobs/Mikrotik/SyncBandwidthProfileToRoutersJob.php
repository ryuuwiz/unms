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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBandwidthProfileToRoutersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 60;

    /**
     * @var array<int, int>
     */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public ProfilBandwidth $profil
    ) {
        $this->onQueue('mikrotik-low');
    }

    /**
     * Dedupe repeated dispatches for the same profile (e.g. a rapid double
     * save) so they don't fan out into duplicate full-router sweeps.
     */
    public function uniqueId(): string
    {
        return (string) $this->profil->id;
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $routers = Router::where('status_koneksi', StatusRouter::Online)->get();

        if ($routers->isEmpty()) {
            return;
        }

        foreach ($routers as $router) {
            // Cooperate with the same per-router lock RecoverPppRouterJob/ProvisionRouterJob
            // hold during a full sync (ADR 0032): skip (don't block/queue-wait) if another
            // job is already talking to this router's RouterOS API, instead of opening a
            // second concurrent socket and contending for router CPU. This job runs again
            // on every profile save and every 15-minute recovery cycle, so a skipped router
            // is picked up again shortly rather than worth blocking the whole job for.
            $lock = Cache::lock("mikrotik:router:{$router->id}", 120);

            if (! $lock->get()) {
                Log::info('Melewati sinkronisasi profil bandwidth: router sedang dikunci oleh proses lain.', [
                    'router_id' => $router->id,
                    'profil_bandwidth_id' => $this->profil->id,
                ]);

                continue;
            }

            try {
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

                    // Profile PPP per Pool merujuk nama pool, jadi pool harus sudah ada di router lebih dulu.
                    foreach ($router->ipPools as $pool) {
                        $mikrotikService->syncIpPool($router, $pool);
                        $mikrotikService->ensurePppProfile($router, $this->profil, null, $pool);
                    }

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
            } finally {
                $lock->release();
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
