<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusRouter;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Mikrotik\NotifikasiNoc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ping router tiap 10 dtk (mikrotik:ping). Log & Notifikasi NOC hanya saat status berubah
 * online <-> offline; pulih dari offline memicu RecoverPppRouterJob.
 */
class PingRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 10;

    public int $uniqueFor = 30;

    public function __construct(
        public Router $router
    ) {
        $this->onQueue('mikrotik-high');
    }

    /**
     * Unique key for lock to avoid duplicate ping jobs for the same router.
     */
    public function uniqueId(): string
    {
        return (string) $this->router->id;
    }

    public function handle(MikrotikService $mikrotikService, NotifikasiNoc $notifikasiNoc): void
    {
        $sebelumnya = $this->router->status_koneksi;

        try {
            // testConnection() menyimpan status_koneksi (Online/Offline) ke router ini.
            $mikrotikService->testConnection($this->router, 3);
        } catch (Throwable) {
            // Router offline: status sudah tercatat; bukan kegagalan job.
        }

        $sekarang = $this->router->status_koneksi;

        if ($sekarang === $sebelumnya) {
            return;
        }

        $online = $sekarang === StatusRouter::Online;

        // Pertama kali terdeteksi online (Unknown) bukan kejadian yang perlu diberitahukan.
        if (! ($online && $sebelumnya === StatusRouter::Unknown)) {
            $log = MikrotikJobLog::create([
                'router_id' => $this->router->id,
                'job_type' => MikrotikJobType::Ping,
                'status' => $online ? MikrotikJobStatus::Success : MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'payload' => ['pesan' => $online
                    ? "Router {$this->router->nama_router} kembali online."
                    : "Router {$this->router->nama_router} offline: {$this->router->last_ping_message}"],
                'error_message' => $online ? null : $this->router->last_ping_message,
                'finished_at' => Carbon::now(),
            ]);

            $notifikasiNoc->kirim($log, whatsapp: true);
        }

        if ($online) {
            RecoverPppRouterJob::dispatch($this->router);
        }
    }
}
