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

/**
 * Satu putaran Ping Router (CONTEXT.md "Ping Router", "Router Offline"). Router Online/Tidak Diketahui
 * dicoba sampai 10x berjeda 30 dtk dan baru menjadi Router Offline setelah kesepuluhnya gagal: satu log +
 * satu notifikasi browser NOC. Router yang sudah Offline cukup satu percobaan; terjangkau = Online +
 * RecoverPppRouterJob tanpa notifikasi. Tanpa WhatsApp: alarm utama NOC untuk router mati adalah PRTG.
 */
class PingRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const JEDA_PERCOBAAN_DETIK = 30;

    public int $tries = 10;

    public int $timeout = 10;

    /**
     * Kunci unik bertahan satu putaran penuh agar pemicu baru tidak membuka putaran kedua. Satu putaran
     * sekitar 5 menit (9 x jeda + 10 x percobaan); dua kali lipatnya memberi cadangan saat mikrotik-high sibuk.
     */
    public int $uniqueFor = 600;

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

        if ($mikrotikService->pingRouter($this->router, config('mikrotik.status_timeout'))) {
            if ($sebelumnya !== StatusRouter::Online) {
                RecoverPppRouterJob::dispatch($this->router);
            }

            return;
        }

        if ($sebelumnya === StatusRouter::Offline) {
            return;
        }

        if ($this->attempts() < $this->tries) {
            $this->release(self::JEDA_PERCOBAAN_DETIK);

            return;
        }

        $this->tandaiRouterOffline($notifikasiNoc);
    }

    private function tandaiRouterOffline(NotifikasiNoc $notifikasiNoc): void
    {
        $this->router->update(['status_koneksi' => StatusRouter::Offline]);

        $log = MikrotikJobLog::create([
            'router_id' => $this->router->id,
            'job_type' => MikrotikJobType::Ping,
            'status' => MikrotikJobStatus::Failed,
            'attempt_count' => $this->attempts(),
            'payload' => ['pesan' => "Router {$this->router->nama_router} offline setelah {$this->attempts()} percobaan: {$this->router->last_ping_message}"],
            'error_message' => $this->router->last_ping_message,
            'finished_at' => Carbon::now(),
        ]);

        $notifikasiNoc->kirim($log);
    }
}
