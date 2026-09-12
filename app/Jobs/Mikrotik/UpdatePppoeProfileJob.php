<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\User;
use App\Notifications\MikrotikJobFailedNotification;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class UpdatePppoeProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public LayananPelanggan $layanan,
        public bool $kickActive = true
    ) {
        $this->onQueue('mikrotik-high');
    }

    public function uniqueId(): string
    {
        return "router:{$this->layanan->router_id}:layanan:{$this->layanan->id}";
    }

    /**
     * Serialize PPPoE lifecycle jobs per physical router (shared across all
     * mikrotik-high job classes) so a burst of jobs for many customers on the
     * same router doesn't open several concurrent RouterOS API sessions and
     * spike the router's CPU. uniqueId() above only dedupes per-customer, not
     * per-router, which is what this middleware closes.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mikrotik-router-{$this->layanan->router_id}"))
                ->releaseAfter(5)
                ->expireAfter(30)
                ->shared(),
        ];
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

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $router = $this->layanan->router;
        if (! $router) {
            return;
        }

        $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
            ->where('job_type', MikrotikJobType::UpdatePppoeProfile)
            ->latest()
            ->first();

        if ($log) {
            $log->update([
                'status' => MikrotikJobStatus::Failed,
                'error_message' => $exception?->getMessage() ?? 'Gagal update profil PPPoE setelah 3 percobaan',
                'finished_at' => Carbon::now(),
            ]);

            $recipients = User::role(['super_admin', 'noc'])->get();
            foreach ($recipients as $recipient) {
                $recipient->notify(new MikrotikJobFailedNotification($log));
            }
        }
    }
}
