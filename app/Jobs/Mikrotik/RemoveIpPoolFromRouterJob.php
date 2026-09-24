<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Bersihkan pool, queue, dan profile per pool dari router setelah IP Pool dihapus atau dipindah router.
 * Menerima nilai primitif karena barisnya sudah tidak ada saat job berjalan. Gagal dicatat, tidak di-retry:
 * sisa objek hanya sampah konfigurasi, bukan gangguan layanan.
 */
class RemoveIpPoolFromRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $routerId,
        public readonly string $poolName,
        public readonly int|string|null $actor = null,
        public readonly string $reason = 'IP Pool dihapus dari UNMS',
    ) {
        $this->onQueue('mikrotik-low');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mikrotik-router-{$this->routerId}-pool-sync"))
                ->releaseAfter(10)
                ->expireAfter(60),
        ];
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $router = Router::find($this->routerId);

        if (! $router) {
            return;
        }

        try {
            $mikrotikService->removeIpPool(
                $router,
                $this->poolName,
                new PppDeletionContext($this->actor ?? 'system:tanpa-user', $this->reason),
            );
        } catch (Throwable $e) {
            MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::DeleteIpPool,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'payload' => ['pool' => $this->poolName, 'actor' => $this->actor, 'reason' => $this->reason],
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);
        }
    }
}
