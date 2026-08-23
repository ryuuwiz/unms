<?php

namespace App\Console\Commands;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Console\Command;

class MikrotikProvisionAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:provisi-layanan 
                            {--router= : ID Router tertentu}
                            {--force : Paksa provisi ulang seluruh layanan meskipun sudah terprovisi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provisi akun PPPoE layanan pelanggan ke router MikroTik secara massal';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikService $mikrotikService): int
    {
        $this->info('====================================================');
        $this->info('      MIKROTIK BATCH PROVISIONING PPPOE             ');
        $this->info('====================================================');

        $routerId = $this->option('router');
        $force = (bool) $this->option('force');

        $query = LayananPelanggan::with(['router', 'paketLayanan.profilBandwidth', 'pelanggan'])
            ->whereNotNull('router_id')
            ->when($routerId, fn ($q) => $q->where('router_id', $routerId))
            ->when(! $force, fn ($q) => $q->where('provisioning_status', '!=', ProvisioningStatus::Success));

        $layanans = $query->get();

        if ($layanans->isEmpty()) {
            $this->info('Tidak ada layanan pelanggan yang perlu diprovisi.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$layanans->count()} layanan untuk diprovisi.");
        $bar = $this->output->createProgressBar($layanans->count());
        $bar->start();

        $successCount = 0;
        $failedCount = 0;

        foreach ($layanans as $layanan) {
            $router = $layanan->router;
            if (! $router) {
                $failedCount++;
                $bar->advance();

                continue;
            }

            try {
                $result = $mikrotikService->createOrUpdatePppoeSecret($router, $layanan);

                MikrotikJobLog::create([
                    'router_id' => $router->id,
                    'layanan_pelanggan_id' => $layanan->id,
                    'job_type' => MikrotikJobType::ProvisionPppoe,
                    'status' => MikrotikJobStatus::Success,
                    'attempt_count' => 1,
                    'payload' => $result,
                    'finished_at' => now(),
                ]);

                $successCount++;
            } catch (\Throwable $e) {
                MikrotikJobLog::create([
                    'router_id' => $router->id,
                    'layanan_pelanggan_id' => $layanan->id,
                    'job_type' => MikrotikJobType::ProvisionPppoe,
                    'status' => MikrotikJobStatus::Failed,
                    'attempt_count' => 1,
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);

                $failedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Berhasil diprovisi : {$successCount}");
        if ($failedCount > 0) {
            $this->warn("⚠️ Gagal diprovisi   : {$failedCount}");
        }

        return self::SUCCESS;
    }
}
