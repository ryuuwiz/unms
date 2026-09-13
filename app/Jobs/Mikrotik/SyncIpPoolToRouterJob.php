<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\IpPool;
use App\Models\MikrotikJobLog;
use App\Models\User;
use App\Notifications\MikrotikJobFailedNotification;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class SyncIpPoolToRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public IpPool $ipPool
    ) {
        $this->onQueue('mikrotik-low');
    }

    /**
     * Bound retries by wall-clock time instead of attempt count.
     *
     * Root cause (Sentry: "SyncIpPoolToRouterJob has been attempted too many
     * times"): WithoutOverlapping::release() re-queues the job when the
     * per-router lock is contested, but the Redis queue driver still counts
     * every pop toward $tries regardless of whether handle() ever ran. With
     * tries=3 and releaseAfter(10), pure lock contention (e.g. several IP
     * pools on the same router saved together while that router is slow to
     * respond) can exhaust all 3 tries in ~20-30s and permanently fail the
     * job via MaxAttemptsExceededException -- before a single RouterOS call
     * was attempted, so no MikrotikJobLog row or real error ever gets
     * recorded. Worker::markJobAsFailedIfAlreadyExceedsMaxAttempts() checks
     * retryUntil() BEFORE the tries count and skips the count-based failure
     * entirely while this window is open, so a contested lock is retried on
     * its normal backoff instead of being silently killed.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * Dedupe repeated dispatches for the same pool (e.g. IpPoolObserver::saved()
     * firing on rapid successive edits, or a staff double-clicking "Terapkan
     * ke Router") so they don't queue up as separate RouterOS API sessions.
     */
    public function uniqueId(): string
    {
        return (string) $this->ipPool->id;
    }

    /**
     * Serialize pool syncs per physical router (same pattern as
     * RecoverPppRouterJob, ADR 0032): a router with several IP pools saved
     * around the same time would otherwise dispatch one job per pool, each
     * opening its own RouterOS API socket concurrently on this auto-scaling
     * mikrotik-low queue (up to 10 parallel workers) and spiking router CPU.
     * uniqueId() above only dedupes the same pool, not sibling pools on the
     * same router, which is what this middleware closes.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mikrotik-router-{$this->ipPool->router_id}-pool-sync"))
                ->releaseAfter(10)
                ->expireAfter(60),
        ];
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $router = $this->ipPool->router;

        if (! $router) {
            return;
        }

        $log = MikrotikJobLog::create([
            'router_id' => $router->id,
            'ip_pool_id' => $this->ipPool->id,
            'job_type' => MikrotikJobType::SyncIpPool,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => $this->attempts(),
            'payload' => [
                'nama_pool' => $this->ipPool->nama_pool,
                'network' => $this->ipPool->labelNetwork(),
                'ranges' => "{$this->ipPool->rentang_ip_awal}-{$this->ipPool->rentang_ip_akhir}",
            ],
        ]);

        try {
            $result = $mikrotikService->syncIpPool($router, $this->ipPool);

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
                'payload' => array_merge($log->payload ?? [], ['result' => $result]),
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
        $router = $this->ipPool->router;
        if (! $router) {
            return;
        }

        $log = MikrotikJobLog::where('ip_pool_id', $this->ipPool->id)
            ->where('job_type', MikrotikJobType::SyncIpPool)
            ->latest()
            ->first();

        if ($log) {
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $exception?->getMessage() ?? 'Gagal sinkronisasi IP Pool setelah 3 percobaan',
                'finished_at' => Carbon::now(),
            ]);

            $recipients = User::role(['super_admin', 'noc'])->get();
            foreach ($recipients as $recipient) {
                $recipient->notify(new MikrotikJobFailedNotification($log));
            }
        }
    }
}
