<?php

namespace App\Console\Commands;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Jobs\Mikrotik\RecoverPppRouterJob;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
                            {--audit-orphans : Hanya laporkan orphaned secret ke log, tidak pernah menghapus}
                            {--async : Jalankan via antrean mikrotik-low di background secara asynchronous}
                            {--dry-run : Simulasi audit drift tanpa mengubah apapun di router (hanya logging, tidak bisa digabung dengan --force)}';

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
        $auditOrphans = (bool) $this->option('audit-orphans');
        $async = (bool) $this->option('async');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun && $force) {
            $this->error('--dry-run tidak bisa digabung dengan --force: mode force selalu menjalankan provisionRouterFull() secara nyata ke router.');

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
            $this->info("Mendispatch job auto-recovery untuk {$routers->count()} router ke antrean mikrotik-low...");
            if ($dryRun) {
                $this->warn('Mode DRY-RUN aktif: job akan mensimulasikan tanpa mengubah apapun di router, hasil dicatat ke log.');
            }
            try {
                foreach ($routers as $router) {
                    RecoverPppRouterJob::dispatch($router, $force, $dryRun, $auditOrphans);
                }
                $this->info('Seluruh job recovery router berhasil dimasukkan ke antrean.');

                return self::SUCCESS;
            } catch (Throwable $e) {
                $this->error("Gagal mendispatch job auto-recovery: {$e->getMessage()}");
                Log::error("Gagal mendispatch job auto-recovery ke antrean mikrotik-low: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
                report($e);

                return self::FAILURE;
            }
        }

        $this->info("Menjalankan pipeline auto-recovery untuk {$routers->count()} router...");
        if ($force) {
            $this->warn('Mode FORCE aktif: Seluruh profil dan secret akan disinkronkan penuh.');
        }
        if ($dryRun) {
            $this->warn('Mode DRY-RUN aktif: TIDAK ADA perubahan nyata yang dikirim ke router — hanya simulasi & logging.');
        }

        $this->newLine();

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($routers as $router) {
            $this->line("Memeriksa router: <comment>{$router->nama_router}</comment> ({$router->ip_address}:{$router->port})...");

            try {
                if ($force) {
                    $res = $mikrotikService->provisionRouterFull(router: $router, force: true);
                    $details = $res['details'] ?? [];
                    $profileStats = $details['profiles'] ?? [];
                    $secretStats = $details['secrets'] ?? [];
                    $orphanStats = $details['orphans'] ?? [];

                    $profileText = ($profileStats['synced'] ?? 0).'/'.($profileStats['total'] ?? 0);
                    $secretText = ($secretStats['recovered'] ?? 0).' dipulihkan / '.($secretStats['disabled'] ?? 0).' isolir';
                    $dupText = '0 dibersihkan';
                    $orphanText = ($orphanStats['orphans_count'] ?? 0).' terdeteksi';
                } else {
                    $stats = $mikrotikService->autoRecoverPppSecrets($router, dryRun: $dryRun);

                    $profileStats = $stats['profiles'];
                    $profileText = $profileStats['synced'].'/'.$profileStats['total'];

                    $recoveredCount = $stats['recovered'];
                    $alreadySyncedCount = $stats['already_synced'];
                    $disabledCount = $stats['disabled'];
                    $duplicatesRemoved = $stats['duplicates_removed'];

                    $secretLabel = $dryRun ? 'akan dipulihkan' : 'dipulihkan';
                    $secretText = "{$recoveredCount} {$secretLabel} / {$alreadySyncedCount} sinkron ({$disabledCount} isolir)";
                    $dupText = "{$duplicatesRemoved} dibersihkan";

                    $orphanText = '-';
                    if ($auditOrphans) {
                        $orphanStats = $mikrotikService->auditOrphanedPppSecrets($router);
                        $orphanText = $orphanStats['orphans_count'].' terdeteksi (audit)';
                    }

                    $shouldLog = $routerId !== null
                        || ($recoveredCount > 0)
                        || ($disabledCount > 0)
                        || ($duplicatesRemoved > 0)
                        || ($stats['errors'] !== []);

                    if ($shouldLog) {
                        MikrotikJobLog::create([
                            'router_id' => $router->id,
                            'job_type' => MikrotikJobType::ReconcilePppoe,
                            'status' => MikrotikJobStatus::Success,
                            'attempt_count' => 1,
                            'payload' => $stats,
                            'finished_at' => Carbon::now(),
                        ]);
                    }
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
                $errorMessage = $e->getMessage();
                $this->error("  ❌ Gagal auto-recover {$router->nama_router}: {$errorMessage}");

                Log::error("Gagal auto-recover PPP pada router {$router->nama_router} ({$router->ip_address}:{$router->port}): {$errorMessage}", [
                    'router_id' => $router->id,
                    'router_name' => $router->nama_router,
                    'ip_address' => $router->ip_address,
                    'port' => $router->port,
                    'exception_class' => get_class($e),
                    'exception' => $e,
                ]);

                report($e);

                try {
                    MikrotikJobLog::create([
                        'router_id' => $router->id,
                        'job_type' => MikrotikJobType::ReconcilePppoe,
                        'status' => MikrotikJobStatus::Failed,
                        'attempt_count' => 1,
                        'error_message' => $errorMessage,
                        'finished_at' => Carbon::now(),
                    ]);
                } catch (Throwable $logEx) {
                    Log::warning("Gagal menyimpan MikrotikJobLog untuk router {$router->nama_router}: {$logEx->getMessage()}", [
                        'router_id' => $router->id,
                        'exception' => $logEx,
                    ]);
                }

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

        return self::SUCCESS;
    }
}
