<?php

namespace App\Console\Commands;

use App\Jobs\Mikrotik\RecoverPppRouterJob;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class MikrotikProvisionRouterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:provisi-router
                            {--router= : ID Router tertentu}
                            {--force : Paksa provisi ulang seluruh layanan meskipun sudah terprovisi}
                            {--clean-orphans : Hapus akun PPP Secret di MikroTik yang tidak terdaftar di UNMS}
                            {--async : Jalankan via antrean mikrotik-low di background secara asynchronous}
                            {--dry-run : Simulasi audit drift tanpa mengubah apapun di router (hanya berlaku bersama --async, karena mode sync selalu memakai provisionRouterFull(); tidak bisa digabung dengan --force)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provisi dan sinkronisasikan seluruh konfigurasi master UNMS (IP Pool, Profil Bandwidth, PPP Secret, Isolir) ke router MikroTik';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikService $mikrotikService): int
    {
        $this->info('====================================================');
        $this->info('    PROVISI & SINKRONISASI MASTER ROUTER MIKROTIK   ');
        $this->info('====================================================');

        $routerId = $this->option('router');
        $force = (bool) $this->option('force');
        $cleanOrphans = (bool) $this->option('clean-orphans');
        $async = (bool) $this->option('async');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun && $force) {
            $this->error('--dry-run tidak bisa digabung dengan --force: mode force selalu menjalankan provisionRouterFull() secara nyata ke router.');

            return self::FAILURE;
        }

        if ($dryRun && ! $async) {
            $this->error('--dry-run pada command ini hanya didukung bersama --async: mode sync selalu memakai pipeline provisionRouterFull() yang di luar scope audit drift. Gunakan "mikrotik:recover-ppp --dry-run" untuk mode sync, atau tambahkan --async.');

            return self::FAILURE;
        }

        $query = Router::query()
            ->when($routerId, fn ($q) => $q->where('id', $routerId));

        $routers = $query->get();

        if ($routers->isEmpty()) {
            if ($routerId) {
                $this->error("Router dengan ID {$routerId} tidak ditemukan.");

                return self::FAILURE;
            }

            $this->warn('Tidak ada router yang terdaftar di UNMS.');

            return self::SUCCESS;
        }

        if ($async) {
            $this->info("Mendispatch job provisi & recovery untuk {$routers->count()} router ke antrean mikrotik-low...");
            if ($dryRun) {
                $this->warn('Mode DRY-RUN aktif: job akan mensimulasikan tanpa mengubah apapun di router, hasil dicatat ke log.');
            }
            try {
                foreach ($routers as $router) {
                    RecoverPppRouterJob::dispatch($router, $force, $cleanOrphans, $dryRun);
                }
                $this->info('Seluruh job recovery & provisi router berhasil dimasukkan ke antrean.');

                return self::SUCCESS;
            } catch (Throwable $e) {
                $this->error("Gagal mendispatch job provisi router: {$e->getMessage()}");
                Log::error("Gagal mendispatch job provisi router ke antrean mikrotik-low: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
                report($e);

                return self::FAILURE;
            }
        }

        $this->info("Menjalankan pipeline provisi untuk {$routers->count()} router...");
        if ($force) {
            $this->warn('Mode FORCE aktif: Seluruh akun akan diprovisi ulang.');
        }
        if ($cleanOrphans) {
            $this->warn('Mode CLEAN ORPHANS aktif: Akun tidak terdaftar akan dihapus.');
        }

        $this->newLine();

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($routers as $router) {
            $this->line("Proses router: <comment>{$router->nama_router}</comment> ({$router->ip_address}:{$router->port})...");

            try {
                $res = $mikrotikService->provisionRouterFull(
                    router: $router,
                    force: $force,
                    cleanOrphans: $cleanOrphans
                );

                $details = $res['details'] ?? [];
                $poolStats = $details['ip_pools'] ?? [];
                $profileStats = $details['profiles'] ?? [];
                $secretStats = $details['secrets'] ?? [];
                $orphanStats = $details['orphans'] ?? [];

                $results[] = [
                    'Router' => $router->nama_router,
                    'IP Address' => "{$router->ip_address}:{$router->port}",
                    'Status' => '✅ Berhasil',
                    'IP Pool' => ($poolStats['synced'] ?? 0).'/'.($poolStats['total'] ?? 0),
                    'Profil' => ($profileStats['synced'] ?? 0).'/'.($profileStats['total'] ?? 0),
                    'PPP Secret' => ($secretStats['recovered'] ?? 0).' synced / '.($secretStats['disabled'] ?? 0).' isolir',
                    'Orphans' => $cleanOrphans
                        ? ($orphanStats['deleted'] ?? 0).' dihapus'
                        : ($orphanStats['orphans_count'] ?? 0).' terdeteksi',
                ];

                $successCount++;
                $this->info("  ✅ Sukses provisi {$router->nama_router}");
            } catch (Throwable $e) {
                $failedCount++;
                $this->error("  ❌ Gagal memprovisi {$router->nama_router}: {$e->getMessage()}");

                $results[] = [
                    'Router' => $router->nama_router,
                    'IP Address' => "{$router->ip_address}:{$router->port}",
                    'Status' => '❌ Gagal',
                    'IP Pool' => '-',
                    'Profil' => '-',
                    'PPP Secret' => '-',
                    'Orphans' => '-',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['Router', 'IP Address', 'Status', 'IP Pool', 'Profil', 'PPP Secret', 'Orphans'],
            $results
        );

        $this->newLine();
        $this->info("✅ Berhasil : {$successCount} router");
        if ($failedCount > 0) {
            $this->warn("⚠️ Gagal    : {$failedCount} router");
        }

        return $failedCount > 0 && $successCount === 0 ? self::FAILURE : self::SUCCESS;
    }
}
