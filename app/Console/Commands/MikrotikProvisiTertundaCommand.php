<?php

namespace App\Console\Commands;

use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\LayananPelanggan;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Provisi Cadangan -- lihat CONTEXT.md. Jaring pengaman bila job antrean tidak pernah
 * memprovisi layanan (job hilang, gagal, atau tidak pernah di-dispatch).
 */
class MikrotikProvisiTertundaCommand extends Command
{
    protected $signature = 'mikrotik:provisi-tertunda {--jeda=5 : Menit tanpa percobaan provisi sebelum layanan diambil alih penjadwal}';

    protected $description = 'Antrekan ulang provisi PPP Secret untuk layanan yang belum terprovisi dan tidak sedang dicoba';

    public function handle(): int
    {
        $batas = now()->subMinutes(max(1, (int) $this->option('jeda')));

        $layanans = LayananPelanggan::query()
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend, StatusLayanan::Proses])
            ->whereNotNull('router_id')
            ->whereNotNull('ppp_username')
            ->where('provisioning_status', '!=', ProvisioningStatus::Success)
            // Router offline dipulihkan oleh ping (offline -> online memicu recovery), bukan di sini.
            ->whereHas('router', fn (Builder $q) => $q->where('status_koneksi', '!=', StatusRouter::Offline))
            // Masih/baru dicoba job antrean: jangan berebut.
            ->whereDoesntHave('jobLogs', fn (Builder $q) => $q
                ->where('job_type', MikrotikJobType::ProvisionPppoe)
                ->where('created_at', '>=', $batas))
            ->get();

        foreach ($layanans as $layanan) {
            // Duplikat dengan job yang masih di antrean diabaikan oleh ShouldBeUnique.
            ProvisionPppoeAccountJob::dispatch($layanan, galatSebelumnya: $layanan->last_provisioning_error);
        }

        $this->info("{$layanans->count()} layanan diantrekan untuk provisi cadangan.");

        return self::SUCCESS;
    }
}
