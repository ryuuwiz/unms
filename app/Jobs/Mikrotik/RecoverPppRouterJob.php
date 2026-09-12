<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\MikrotikJobLog;
use App\Models\Router;
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
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecoverPppRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public Router $router,
        public bool $force = false,
        public bool $cleanOrphans = false,
        public bool $dryRun = false
    ) {
        $this->onQueue('mikrotik-low');
    }

    /**
     * Unique key for lock to avoid duplicate recovery jobs for the same router.
     */
    public function uniqueId(): string
    {
        return (string) $this->router->id;
    }

    /**
     * Prevent this full-router-sync job from overlapping itself for the same router,
     * e.g. a scheduled run still processing when a manual --async run fires.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mikrotik-router-{$this->router->id}"))
                ->releaseAfter(180)
                ->expireAfter(600),
        ];
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $lock = Cache::lock("mikrotik:router:{$this->router->id}", 120);

        try {
            $lock->block(15, function () use ($mikrotikService) {
                if ($this->force) {
                    $result = $mikrotikService->provisionRouterFull(
                        router: $this->router,
                        force: true,
                        cleanOrphans: $this->cleanOrphans
                    );
                } else {
                    $result = $mikrotikService->autoRecoverPppSecrets($this->router, dryRun: $this->dryRun);

                    if ($this->cleanOrphans) {
                        // Saat dry-run, jangan benar-benar hapus orphan — hanya laporkan (executeDelete = false).
                        $orphanStats = $mikrotikService->cleanOrphanedPppSecrets($this->router, ! $this->dryRun);
                        $result['orphans'] = $orphanStats;
                    }

                    $recoveredCount = $result['recovered'] ?? 0;
                    $disabledCount = $result['disabled'] ?? 0;
                    $duplicatesRemoved = $result['duplicates_removed'] ?? 0;
                    $errors = $result['errors'] ?? [];

                    $shouldLog = $this->cleanOrphans
                        || ($recoveredCount > 0)
                        || ($disabledCount > 0)
                        || ($duplicatesRemoved > 0)
                        || (! empty($errors));

                    if ($shouldLog) {
                        MikrotikJobLog::create([
                            'router_id' => $this->router->id,
                            'job_type' => MikrotikJobType::ReconcilePppoe,
                            'status' => empty($errors) ? MikrotikJobStatus::Success : MikrotikJobStatus::Failed,
                            'attempt_count' => 1,
                            'payload' => $result,
                            'error_message' => ! empty($errors) ? implode('; ', $errors) : null,
                            'finished_at' => Carbon::now(),
                        ]);
                    }
                }
            });
        } catch (Throwable $e) {
            MikrotikJobLog::create([
                'router_id' => $this->router->id,
                'job_type' => MikrotikJobType::ReconcilePppoe,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = $this->router->jobLogs()
            ->where('job_type', MikrotikJobType::ReconcilePppoe)
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
