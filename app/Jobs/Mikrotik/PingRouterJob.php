<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class PingRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 15;

    public int $uniqueFor = 300;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    public function __construct(
        public Router $router
    ) {
        $this->onQueue('mikrotik');
    }

    /**
     * Unique key for lock to avoid duplicate ping jobs for the same router.
     */
    public function uniqueId(): string
    {
        return (string) $this->router->id;
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
            $result = $mikrotikService->testConnection($this->router, 6);

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
                'payload' => $result,
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
}
