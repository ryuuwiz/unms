<?php

namespace App\Console\Commands;

use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Console\Command;

class MikrotikSyncProfilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:sync-profil
                            {--router= : ID Router tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasikan seluruh profil bandwidth (dengan rate-limit bps biner) ke router MikroTik';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikService $mikrotikService): int
    {
        $this->info('====================================================');
        $this->info('      SINKRONISASI PROFIL BANDWIDTH MIKROTIK        ');
        $this->info('====================================================');

        $routerId = $this->option('router');

        $routers = Router::query()
            ->when($routerId, fn ($q) => $q->where('id', $routerId))
            ->get();

        if ($routers->isEmpty()) {
            if ($routerId) {
                $this->error("Router dengan ID {$routerId} tidak ditemukan.");

                return self::FAILURE;
            }

            $this->warn('Tidak ada router yang terdaftar di UNMS.');

            return self::SUCCESS;
        }

        $profils = ProfilBandwidth::all();

        if ($profils->isEmpty()) {
            $this->warn('Tidak ada profil bandwidth yang terdaftar di UNMS.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$profils->count()} profil bandwidth untuk disinkronisasikan ke {$routers->count()} router.");
        $this->newLine();

        $this->table(
            ['ID', 'Nama Profil', 'Kecepatan', 'Rate-Limit RouterOS (bps)'],
            $profils->map(fn ($p) => [
                $p->id,
                $p->nama_bandwidth,
                $p->labelKecepatan(),
                $p->routerOsRateLimit(),
            ])
        );

        $this->newLine();

        foreach ($routers as $router) {
            $this->line("Proses router: <comment>{$router->nama_router}</comment> ({$router->ip_address}:{$router->port})...");

            try {
                $result = $mikrotikService->syncAllBandwidthProfiles($router);

                $this->info("  ✅ Sukses: {$result['synced']}/{$result['total']} profil tersinkronisasi ke {$router->nama_router}");

                if (! empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        $this->warn("  ⚠️ {$err}");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  ❌ Gagal terhubung ke {$router->nama_router}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Proses sinkronisasi profil bandwidth selesai.');

        return self::SUCCESS;
    }
}
