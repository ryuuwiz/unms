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
        public bool $cleanOrphans = false
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
                    $result = $mikrotikService->autoRecoverPppSecrets($this->router);

                    if ($this->cleanOrphans) {
                        $orphanStats = $mikrotikService->cleanOrphanedPppSecrets($this->router, true);
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
