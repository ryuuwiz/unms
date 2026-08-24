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
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProvisionRouterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public Router $router,
        public bool $force = false,
        public bool $cleanOrphans = false
    ) {
        $this->onQueue('mikrotik');
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
