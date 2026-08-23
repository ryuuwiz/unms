<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class PingRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    public function __construct(
        public Router $router
    ) {
        $this->onQueue('mikrotik');
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $log = MikrotikJobLog::create([
            'router_id' => $this->router->id,
            'job_type' => MikrotikJobType::Ping,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => $this->attempts(),
        ]);

        try {
            $result = $mikrotikService->testConnection($this->router, 5);

            // Auto-recover data PPP jika ada akun yang hilang/tidak sinkron di RouterOS
            $recoveryStats = $mikrotikService->autoRecoverPppSecrets($this->router);

            $payload = array_merge($result, [
                'auto_recovery' => $recoveryStats,
            ]);

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
                'payload' => $payload,
            ]);

            // Catat log khusus jika ada akun yang berhasil di-recover
            if (($recoveryStats['recovered'] ?? 0) > 0) {
                MikrotikJobLog::create([
                    'router_id' => $this->router->id,
                    'job_type' => MikrotikJobType::ReconcilePppoe,
                    'status' => MikrotikJobStatus::Success,
                    'attempt_count' => 1,
                    'payload' => $recoveryStats,
                    'finished_at' => Carbon::now(),
                ]);
            }
        } catch (Throwable $e) {
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            throw $e;
        }
    }
}
