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

/**
 * Hapus PPP Secret dari router lama saat layanan pelanggan pindah router.
 *
 * Job ini dipanggil oleh LayananPelangganObserver ketika router_id berubah.
 * Jika router lama offline, kegagalan dicatat di MikrotikJobLog sebagai Failed
 * tanpa melempar exception agar tidak memblokir proses update layanan.
 */
class CleanupPppSecretOnOldRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $oldRouterId,
        public readonly string $pppUsername,
        public readonly ?int $layananPelangganId = null,
    ) {
        $this->onQueue('mikrotik-high');
    }

    public function uniqueId(): string
    {
        return "router:{$this->oldRouterId}:layanan:{$this->layananPelangganId}";
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $router = Router::find($this->oldRouterId);

        if (! $router) {
            return;
        }

        $log = MikrotikJobLog::create([
            'router_id' => $router->id,
            'layanan_pelanggan_id' => $this->layananPelangganId,
            'job_type' => MikrotikJobType::DeletePppoe,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => 1,
            'payload' => [
                'username' => $this->pppUsername,
                'reason' => 'router_migration_cleanup',
            ],
        ]);

        try {
            $mikrotikService->deletePppoeSecret($router, $this->pppUsername);

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            // Jika router lama offline atau gagal: catat sebagai Failed.
            // Tidak di-throw agar tidak memblokir update layanan.
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);
        }
    }
}
