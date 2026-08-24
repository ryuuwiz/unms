<?php

namespace App\Console\Commands;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class MikrotikRecoverPppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:recover-ppp
                            {--router= : ID Router tertentu}
                            {--force : Paksa sinkronisasi ulang seluruh profil dan akun PPP}
                            {--clean-orphans : Hapus akun PPP Secret di MikroTik yang tidak terdaftar di UNMS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-recover dan sinkronisasikan seluruh profil bandwidth (PPP Profile) dan akun PPP Secret di router MikroTik dari UNMS';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikService $mikrotikService): int
    {
        $this->info('====================================================');
        $this->info('  AUTO-RECOVER PPP PROFILES & SECRETS MIKROTIK      ');
        $this->info('====================================================');

        $routerId = $this->option('router');
        $force = (bool) $this->option('force');
        $cleanOrphans = (bool) $this->option('clean-orphans');

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

        $this->info("Menjalankan pipeline auto-recovery untuk {$routers->count()} router...");
        if ($force) {
            $this->warn('Mode FORCE aktif: Seluruh profil dan secret akan disinkronkan penuh.');
        }
        if ($cleanOrphans) {
            $this->warn('Mode CLEAN ORPHANS aktif: Akun tidak terdaftar di UNMS akan dibersihkan.');
        }

        $this->newLine();

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($routers as $router) {
            $this->line("Memeriksa router: <comment>{$router->nama_router}</comment> ({$router->ip_address}:{$router->port})...");

            try {
                if ($force) {
                    $res = $mikrotikService->provisionRouterFull(
                        router: $router,
                        force: true,
                        cleanOrphans: $cleanOrphans
                    );
                    $details = $res['details'] ?? [];
                    $profileStats = $details['profiles'] ?? [];
                    $secretStats = $details['secrets'] ?? [];
                    $orphanStats = $details['orphans'] ?? [];

                    $profileText = ($profileStats['synced'] ?? 0).'/'.($profileStats['total'] ?? 0);
                    $secretText = ($secretStats['recovered'] ?? 0).' dipulihkan / '.($secretStats['disabled'] ?? 0).' isolir';
                    $dupText = '0 dibersihkan';
                    $orphanText = $cleanOrphans
                        ? ($orphanStats['deleted'] ?? 0).' dihapus'
                        : ($orphanStats['orphans_count'] ?? 0).' terdeteksi';
                } else {
                    $stats = $mikrotikService->autoRecoverPppSecrets($router);

                    $profileStats = $stats['profiles'] ?? [];
                    $profileText = ($profileStats['synced'] ?? 0).'/'.($profileStats['total'] ?? 0);

                    $recoveredCount = $stats['recovered'] ?? 0;
                    $alreadySyncedCount = $stats['already_synced'] ?? 0;
                    $disabledCount = $stats['disabled'] ?? 0;
                    $duplicatesRemoved = $stats['duplicates_removed'] ?? 0;

                    $secretText = "{$recoveredCount} dipulihkan / {$alreadySyncedCount} sinkron ({$disabledCount} isolir)";
                    $dupText = "{$duplicatesRemoved} dibersihkan";

                    $orphanText = '-';
                    if ($cleanOrphans) {
                        $orphanStats = $mikrotikService->cleanOrphanedPppSecrets($router, true);
                        $orphanText = ($orphanStats['deleted'] ?? 0).' dihapus';
                    }

                    MikrotikJobLog::create([
                        'router_id' => $router->id,
                        'job_type' => MikrotikJobType::ReconcilePppoe,
                        'status' => MikrotikJobStatus::Success,
                        'attempt_count' => 1,
                        'payload' => $stats,
                        'finished_at' => Carbon::now(),
                    ]);
                }

                $results[] = [
                    'Router' => $router->nama_router,
                    'IP Address' => "{$router->ip_address}:{$router->port}",
                    'Status' => '✅ Berhasil',
                    'PPP Profile' => $profileText,
                    'PPP Secret' => $secretText,
                    'Duplikat' => $dupText,
                    'Orphans' => $orphanText,
                ];

                $successCount++;
                $this->info("  ✅ Sukses auto-recover {$router->nama_router}");
            } catch (Throwable $e) {
                $failedCount++;
                $this->error("  ❌ Gagal auto-recover {$router->nama_router}: {$e->getMessage()}");

                MikrotikJobLog::create([
                    'router_id' => $router->id,
                    'job_type' => MikrotikJobType::ReconcilePppoe,
                    'status' => MikrotikJobStatus::Failed,
                    'attempt_count' => 1,
                    'error_message' => $e->getMessage(),
                    'finished_at' => Carbon::now(),
                ]);

                $results[] = [
                    'Router' => $router->nama_router,
                    'IP Address' => "{$router->ip_address}:{$router->port}",
                    'Status' => '❌ Gagal',
                    'PPP Profile' => '-',
                    'PPP Secret' => '-',
                    'Duplikat' => '-',
                    'Orphans' => '-',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['Router', 'IP Address', 'Status', 'PPP Profile', 'PPP Secret', 'Duplikat', 'Orphans'],
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
