<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\Concerns\AntreanLayananRouter;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class DisablePppoeAccountJob implements ShouldBeUnique, ShouldQueue
{
    use AntreanLayananRouter, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public LayananPelanggan $layanan,
        public bool $disconnectActive = true
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

        // Job bisa tertunda/tersusun ulang di antrean: status dibaca ulang saat dieksekusi. Layanan yang sudah Aktif
        // kembali (mis. baru bayar) tidak boleh diisolir oleh job Suspend yang basi.
        if ($this->layanan->status === StatusLayanan::Aktif) {
            MikrotikJobLog::create([
                'router_id' => $router->id,
                'layanan_pelanggan_id' => $this->layanan->id,
                'job_type' => MikrotikJobType::DisablePppoe,
                'status' => MikrotikJobStatus::Dilewati,
                'attempt_count' => $this->attempts(),
                'payload' => ['username' => $this->layanan->ppp_username],
                'error_message' => 'Status layanan sudah Aktif saat job dieksekusi; isolir dibatalkan.',
                'finished_at' => Carbon::now(),
            ]);

            return;
        }

        // Gate proaktif: sama seperti EnablePppoeAccountJob -- lihat komentar di sana.
        if ($router->status_koneksi === StatusRouter::Offline) {
            MikrotikJobLog::create([
                'router_id' => $router->id,
                'layanan_pelanggan_id' => $this->layanan->id,
                'job_type' => MikrotikJobType::DisablePppoe,
                'status' => MikrotikJobStatus::Dilewati,
                'attempt_count' => $this->attempts(),
                'payload' => ['username' => $this->layanan->ppp_username, 'disconnect_active' => $this->disconnectActive],
                'error_message' => "Router {$router->nama_router} diketahui offline; percobaan dilewati, akan disinkronkan otomatis oleh siklus rekonsiliasi berikutnya.",
                'finished_at' => Carbon::now(),
            ]);

            return;
        }

        $log = MikrotikJobLog::create([
            'router_id' => $router->id,
            'layanan_pelanggan_id' => $this->layanan->id,
            'job_type' => MikrotikJobType::DisablePppoe,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => $this->attempts(),
            'payload' => [
                'username' => $this->layanan->ppp_username,
                'disconnect_active' => $this->disconnectActive,
            ],
        ]);

        try {
            $mikrotikService->disablePppoeSecret($router, $this->layanan, $this->disconnectActive);

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
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
    }

    public function failed(?Throwable $exception): void
    {
        $this->laporkanKegagalanAkhir(
            MikrotikJobType::DisablePppoe,
            $this->layanan->router_id,
            $this->layanan->id,
            $exception,
            ['username' => $this->layanan->ppp_username],
        );
    }
}
