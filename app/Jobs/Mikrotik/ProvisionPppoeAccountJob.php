<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
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

class ProvisionPppoeAccountJob implements ShouldBeUnique, ShouldQueue
{
    use AntreanLayananRouter, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public LayananPelanggan $layanan,
        public bool $kickActive = false,
        /** Diisi Provisi Cadangan: galat sama dengan percobaan sebelumnya tidak diberitahukan ulang. */
        public ?string $galatSebelumnya = null,
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

        $log = MikrotikJobLog::create([
            'router_id' => $router->id,
            'layanan_pelanggan_id' => $this->layanan->id,
            'job_type' => MikrotikJobType::ProvisionPppoe,
            'status' => MikrotikJobStatus::Pending,
            'attempt_count' => $this->attempts(),
            'payload' => [
                'username' => $this->layanan->ppp_username,
                'jenis_koneksi' => $this->layanan->jenis_koneksi->value,
                'ip_static' => $this->layanan->ip_static,
            ],
        ]);

        try {
            $result = $mikrotikService->createOrUpdatePppoeSecret($router, $this->layanan);

            // Perubahan alamat (mis. IP Publik) tidak berlaku ke sesi berjalan sampai sesi diputus.
            if ($this->kickActive) {
                $mikrotikService->removeActiveSession($router, (string) $this->layanan->ppp_username);
            }

            $log->update([
                'status' => MikrotikJobStatus::Success,
                'finished_at' => Carbon::now(),
                'payload' => array_merge($log->payload ?? [], ['result' => $result]),
            ]);

            // Jika status masih Proses, ubah menjadi Aktif
            if ($this->layanan->status === StatusLayanan::Proses) {
                $this->layanan->update(['status' => StatusLayanan::Aktif]);
            }
        } catch (Throwable $e) {
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            // Galat konfigurasi/data tidak akan berhasil dengan retry; hanya galat koneksi yang dicoba lagi.
            $this->gagalkanAtauCobaLagi($e);

            return;
        }

        app(NotifikasiNoc::class)->kirim($log->refresh());
    }

    public function failed(?Throwable $exception): void
    {
        $this->laporkanKegagalanAkhir(
            MikrotikJobType::ProvisionPppoe,
            $this->layanan->router_id,
            $this->layanan->id,
            $exception,
            ['username' => $this->layanan->ppp_username],
            beriTahu: ! ($this->galatSebelumnya && $exception && str_contains($exception->getMessage(), $this->galatSebelumnya)),
        );
    }
}
