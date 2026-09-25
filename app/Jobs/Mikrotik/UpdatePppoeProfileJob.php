<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Jobs\Mikrotik\Concerns\AntreanLayananRouter;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
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

class UpdatePppoeProfileJob implements ShouldBeUnique, ShouldQueue
{
    use AntreanLayananRouter, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public LayananPelanggan $layanan,
        public bool $kickActive = true
    ) {
        $this->onQueue('mikrotik-high');
        $this->tandaiWaktuDispatch();
    }

    public function uniqueId(): string
    {
        return "router:{$this->layanan->router_id}:layanan:{$this->layanan->id}";
    }

    protected function routerIdUntukKunci(): int
    {
        return (int) $this->layanan->router_id;
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $router = $this->layanan->router;

        if (! $router) {
            return;
        }

        $paket = $this->layanan->paketLayanan;
        $profil = $paket?->profilBandwidth;

        $log = MikrotikJobLog::create([
            'router_id' => $router->id,
            'layanan_pelanggan_id' => $this->layanan->id,
            'job_type' => MikrotikJobType::UpdatePppoeProfile,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => $this->attempts(),
            'payload' => [
                'username' => $this->layanan->ppp_username,
                'paket_id' => $paket?->id,
                'paket_nama' => $paket?->nama_paket,
                'bandwidth_profile' => $profil?->nama_bandwidth,
                'kick_active' => $this->kickActive,
            ],
        ]);

        try {
            $result = $mikrotikService->updatePppoeProfile($router, $this->layanan, $this->kickActive);

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

            $this->gagalkanAtauCobaLagi($e);

            return;
        }

        app(NotifikasiNoc::class)->kirim($log->refresh());
    }

    public function failed(?Throwable $exception): void
    {
        $this->laporkanKegagalanAkhir(
            MikrotikJobType::UpdatePppoeProfile,
            $this->layanan->router_id,
            $this->layanan->id,
            $exception,
            ['username' => $this->layanan->ppp_username],
        );
    }
}
