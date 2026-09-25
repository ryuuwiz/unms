<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Jobs\Mikrotik\Concerns\AntreanLayananRouter;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;
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
 * Galat koneksi (router lama offline) di-retry sampai batas waktu antrean; kegagalan akhir
 * tercatat di MikrotikJobLog dan diberitahukan ke NOC.
 */
class CleanupPppSecretOnOldRouterJob implements ShouldBeUnique, ShouldQueue
{
    use AntreanLayananRouter, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $oldRouterId,
        public readonly string $pppUsername,
        public readonly ?int $layananPelangganId = null,
        public readonly int|string|null $actor = null,
        public readonly string $reason = 'Pembersihan secret lama (pindah router / ganti username / layanan dihapus)',
    ) {
        $this->onQueue('mikrotik-high');
        $this->tandaiWaktuDispatch();
    }

    public function uniqueId(): string
    {
        return "router:{$this->oldRouterId}:layanan:{$this->layananPelangganId}";
    }

    protected function routerIdUntukKunci(): int
    {
        return $this->oldRouterId;
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
            'attempt_count' => $this->attempts(),
            'payload' => [
                'username' => $this->pppUsername,
                'actor' => $this->actor ?? 'system:tanpa-user',
                'reason' => $this->reason,
            ],
        ]);

        try {
            $dihapus = $mikrotikService->deletePppoeSecret(
                $router,
                $this->pppUsername,
                new PppDeletionContext($this->actor ?? 'system:tanpa-user', $this->reason),
            );

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
                'payload' => array_merge($log->payload ?? [], ['deleted' => $dihapus]),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            $this->gagalkanAtauCobaLagi($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->laporkanKegagalanAkhir(
            MikrotikJobType::DeletePppoe,
            $this->oldRouterId,
            $this->layananPelangganId,
            $exception,
            ['username' => $this->pppUsername, 'reason' => $this->reason],
        );
    }
}
