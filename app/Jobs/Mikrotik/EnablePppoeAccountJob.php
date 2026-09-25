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
use App\Services\Mikrotik\NotifikasiNoc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class EnablePppoeAccountJob implements ShouldBeUnique, ShouldQueue
{
    use AntreanLayananRouter, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public LayananPelanggan $layanan
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

        // Job bisa tertunda/tersusun ulang di antrean: layanan yang sudah Suspend/Berhenti lagi tidak boleh dibuka
        // oleh job Enable yang basi.
        if (in_array($this->layanan->status, [StatusLayanan::Suspend, StatusLayanan::Berhenti], true)) {
            MikrotikJobLog::create([
                'router_id' => $router->id,
                'layanan_pelanggan_id' => $this->layanan->id,
                'job_type' => MikrotikJobType::EnablePppoe,
                'status' => MikrotikJobStatus::Dilewati,
                'attempt_count' => $this->attempts(),
                'payload' => ['username' => $this->layanan->ppp_username],
                'error_message' => "Status layanan {$this->layanan->status->value} saat job dieksekusi; aktivasi dibatalkan.",
                'finished_at' => Carbon::now(),
            ]);

            return;
        }

        // Gate proaktif: kalau router diketahui offline dari ping terakhir (mikrotik:ping,
        // tiap 5 menit), jangan buang percobaan koneksi -- biarkan siklus rekonsiliasi
        // autoRecoverPppSecrets() (tiap 15 menit) yang menyamakan status disabled begitu
        // router kembali online. Kredensial yang benar-benar salah (bukan router mati)
        // tetap tertangkap oleh jalur reaktif getClient() di bawah seperti biasa.
        if ($router->status_koneksi === StatusRouter::Offline) {
            MikrotikJobLog::create([
                'router_id' => $router->id,
                'layanan_pelanggan_id' => $this->layanan->id,
                'job_type' => MikrotikJobType::EnablePppoe,
                'status' => MikrotikJobStatus::Dilewati,
                'attempt_count' => $this->attempts(),
                'payload' => ['username' => $this->layanan->ppp_username],
                'error_message' => "Router {$router->nama_router} diketahui offline; percobaan dilewati, akan disinkronkan otomatis oleh siklus rekonsiliasi berikutnya.",
                'finished_at' => Carbon::now(),
            ]);

            return;
        }

        $log = MikrotikJobLog::create([
            'router_id' => $router->id,
            'layanan_pelanggan_id' => $this->layanan->id,
            'job_type' => MikrotikJobType::EnablePppoe,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => $this->attempts(),
            'payload' => [
                'username' => $this->layanan->ppp_username,
            ],
        ]);

        try {
            $mikrotikService->enablePppoeSecret($router, $this->layanan);

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

        app(NotifikasiNoc::class)->kirim($log->refresh());
    }

    public function failed(?Throwable $exception): void
    {
        $this->laporkanKegagalanAkhir(
            MikrotikJobType::EnablePppoe,
            $this->layanan->router_id,
            $this->layanan->id,
            $exception,
            ['username' => $this->layanan->ppp_username],
        );
    }
}
