<?php

namespace App\Jobs\Mikrotik;

use App\Models\Router;
use App\Models\User;
use App\Notifications\MikrotikJobFailedNotification;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Pipeline penuh bisa beberapa menit; harus < retry_after antrean (config/queue.php). */
    public int $timeout = 300;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public Router $router,
        public bool $force = false,
        public bool $cleanOrphans = false
    ) {
        $this->onQueue('mikrotik-low');
    }

    /**
     * Same per-router lock key as RecoverPppRouterJob (ADR 0032): this job
     * calls the same provisionRouterFull() pipeline, so it must not run
     * concurrently against a router that another full-sync job already holds.
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
        $mikrotikService->provisionRouterFull(
            router: $this->router,
            force: $this->force,
            cleanOrphans: $this->cleanOrphans
        );
    }

    public function failed(?Throwable $exception): void
    {
        $recipients = User::role(['super_admin', 'noc'])->get();

        $log = $this->router->jobLogs()
            ->latest()
            ->first();

        if ($log) {
            foreach ($recipients as $recipient) {
                $recipient->notify(new MikrotikJobFailedNotification($log));
            }
        }
    }
}
