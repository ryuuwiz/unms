<?php

namespace App\Jobs\Mikrotik;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusRouter;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Models\User;
use App\Notifications\MikrotikJobFailedNotification;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecoverPppRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public Router $router,
        public bool $force = false,
        public bool $cleanOrphans = false,
        public bool $dryRun = false,
        public bool $auditOrphans = false
    ) {
        $this->onQueue('mikrotik-low');
    }

    /**
     * Unique key for lock to avoid duplicate recovery jobs for the same router.
     */
    public function uniqueId(): string
    {
        return (string) $this->router->id;
    }

    /**
     * Prevent this full-router-sync job from overlapping itself for the same router,
     * e.g. a scheduled run still processing when a manual --async run fires.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mikrotik-router-{$this->router->id}"))
                ->releaseAfter(180)
                ->expireAfter(600),
        ];
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        // Router yang diketahui offline (ping 5 menit) dilewati: tanpa ini job gagal dan menotifikasi tiap siklus 15 menit.
        if ($this->router->fresh()?->status_koneksi === StatusRouter::Offline) {
            MikrotikJobLog::create([
                'router_id' => $this->router->id,
                'job_type' => MikrotikJobType::ReconcilePppoe,
                'status' => MikrotikJobStatus::Dilewati,
                'attempt_count' => 1,
                'error_message' => "Router {$this->router->nama_router} diketahui offline; rekonsiliasi dilewati sampai router online kembali.",
                'finished_at' => Carbon::now(),
            ]);

            return;
        }

        $lock = Cache::lock("mikrotik:router:{$this->router->id}", 120);

        try {
            $lock->block(15, function () use ($mikrotikService) {
                if ($this->force) {
                    $result = $mikrotikService->provisionRouterFull(
                        router: $this->router,
                        force: true,
                        cleanOrphans: $this->cleanOrphans
                    );
                } else {
                    $result = $mikrotikService->autoRecoverPppSecrets($this->router, dryRun: $this->dryRun);

                    if ($this->cleanOrphans) {
                        // Saat dry-run, jangan benar-benar hapus orphan — hanya laporkan (executeDelete = false).
                        $orphanStats = $mikrotikService->cleanOrphanedPppSecrets(
                            $this->router,
                            ! $this->dryRun,
                            null,
                            $this->dryRun ? null : PppDeletionContext::system('artisan', 'Pembersihan orphaned secret atas permintaan eksplisit (opsi --clean-orphans)'),
                        );
                        $result['orphans'] = $orphanStats;
                    } elseif ($this->auditOrphans) {
                        // Audit terjadwal: hanya melaporkan, tidak pernah menghapus.
                        $result['orphans'] = $mikrotikService->cleanOrphanedPppSecrets($this->router, false);
                    }

                    $recoveredCount = $result['recovered'] ?? 0;
                    $disabledCount = $result['disabled'] ?? 0;
                    $duplicatesRemoved = $result['duplicates_removed'] ?? 0;
                    $errors = $result['errors'] ?? [];

                    $orphanCount = (int) ($result['orphans']['orphans_count'] ?? 0);
                    $capExceeded = (bool) ($result['delete_cap_exceeded'] ?? false) || (bool) ($result['orphans']['cap_exceeded'] ?? false);
                    $errors = array_merge($errors, $result['orphans']['errors'] ?? []);
                    $shouldLog = $this->cleanOrphans
                        || ($this->auditOrphans && $orphanCount > 0)
                        || ($recoveredCount > 0)
                        || ($disabledCount > 0)
                        || ($duplicatesRemoved > 0)
                        || (! empty($errors));

                    if ($shouldLog) {
                        $log = MikrotikJobLog::create([
                            'router_id' => $this->router->id,
                            'job_type' => MikrotikJobType::ReconcilePppoe,
                            'status' => empty($errors) ? MikrotikJobStatus::Success : MikrotikJobStatus::Failed,
                            'attempt_count' => 1,
                            'payload' => $result,
                            'error_message' => ! empty($errors) ? implode('; ', $errors) : null,
                            'finished_at' => Carbon::now(),
                        ]);

                        // Batas hapus massal tercapai: NOC wajib tahu (sekali per router per jam).
                        if ($capExceeded) {
                            $this->notifyOncePerHour('delete-cap', $log);
                        }
                    }
                }
            });
        } catch (Throwable $e) {
            MikrotikJobLog::create([
                'router_id' => $this->router->id,
                'job_type' => MikrotikJobType::ReconcilePppoe,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = $this->router->jobLogs()
            ->where('job_type', MikrotikJobType::ReconcilePppoe)
            ->latest()
            ->first();

        if ($log) {
            $this->notifyOncePerHour('failed', $log);
        }
    }

    /**
     * Notifikasi ke super_admin/NOC dibatasi sekali per router per jam per jenis: router yang mati tidak
     * boleh membanjiri notifikasi tiap siklus rekonsiliasi.
     */
    private function notifyOncePerHour(string $jenis, MikrotikJobLog $log): void
    {
        if (! Cache::add("mikrotik:notif:{$this->router->id}:{$jenis}", true, 3600)) {
            return;
        }

        foreach (User::role(['super_admin', 'noc'])->get() as $recipient) {
            $recipient->notify(new MikrotikJobFailedNotification($log));
        }
    }
}
