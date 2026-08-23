<?php

namespace App\Console\Commands;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Console\Command;
use Throwable;

class MigratePppUsernameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'layanan:migrate-ppp-username
                            {--dry-run : Tampilkan preview perubahan tanpa mengeksekusi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrasi format ppp_username seluruh layanan ke format No.Reg_NNNNN, hapus secret lama dari MikroTik, dan provisi ulang';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikService $mikrotikService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('============================================================');
        $this->info('      MIGRASI FORMAT PPP USERNAME: No.Reg_NNNNN             ');
        $this->info('============================================================');

        if ($dryRun) {
            $this->warn('⚠️  MODE DRY-RUN: tidak ada perubahan yang akan disimpan.');
            $this->newLine();
        } else {
            $this->warn('⚠️  PERINGATAN: Operasi ini akan memutus sesi PPPoE aktif pelanggan sementara.');
            $this->warn('   Disarankan dijalankan saat maintenance window.');
            $this->newLine();

            if (! $this->confirm('Lanjutkan migrasi?', false)) {
                $this->info('Migrasi dibatalkan.');

                return self::SUCCESS;
            }
        }

        $layanans = LayananPelanggan::with(['pelanggan', 'router'])
            ->whereNotNull('router_id')
            ->get();

        if ($layanans->isEmpty()) {
            $this->info('Tidak ada layanan yang perlu dimigrasi.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$layanans->count()} layanan untuk dimigrasi.");
        $this->newLine();

        $bar = $this->output->createProgressBar($layanans->count());
        $bar->start();

        $successCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($layanans as $layanan) {
            $pelanggan = $layanan->pelanggan;
            $router = $layanan->router;

            if (! $pelanggan || ! $router) {
                $this->newLine();
                $this->warn("  [SKIP] Layanan ID {$layanan->id} — relasi pelanggan/router tidak ditemukan.");
                $skippedCount++;
                $bar->advance();

                continue;
            }

            $oldUsername = $layanan->ppp_username;

            // Skip jika sudah berformat {no_reg}_{5 digit} — tidak perlu dimigrasi ulang
            $expectedPrefix = $pelanggan->no_reg.'_';
            if (str_starts_with($oldUsername, $expectedPrefix) && LayananPelanggan::extractCounter($oldUsername) !== null) {
                $skippedCount++;
                $bar->advance();

                continue;
            }

            $newUsername = LayananPelanggan::generatePppUsername($pelanggan);

            if ($dryRun) {
                $this->newLine();
                $this->line("  [PREVIEW] {$oldUsername} -> {$newUsername} (Router: {$router->nama_router})");
                $successCount++;
                $bar->advance();

                continue;
            }

            try {
                // 1. Hapus secret lama dari MikroTik
                $mikrotikService->deletePppoeSecret($router, $oldUsername);

                // 2. Update ppp_username di database
                $layanan->update(['ppp_username' => $newUsername]);

                // 3. Langsung provision secret baru ke router (sync — tidak via queue)
                //    agar tidak ada window downtime antara delete dan create
                $layanan->load(['pelanggan', 'paketLayanan.profilBandwidth']);
                $mikrotikService->createOrUpdatePppoeSecret($router, $layanan->fresh(['pelanggan', 'paketLayanan.profilBandwidth']));

                MikrotikJobLog::create([
                    'router_id' => $router->id,
                    'layanan_pelanggan_id' => $layanan->id,
                    'job_type' => MikrotikJobType::ProvisionPppoe,
                    'status' => MikrotikJobStatus::Success,
                    'attempt_count' => 1,
                    'payload' => ['migration' => true, 'old_username' => $oldUsername, 'new_username' => $newUsername],
                    'finished_at' => now(),
                ]);

                $successCount++;
            } catch (Throwable $e) {
                $this->newLine();
                $this->error("  [GAGAL] {$oldUsername} — {$e->getMessage()}");

                MikrotikJobLog::create([
                    'router_id' => $router->id,
                    'layanan_pelanggan_id' => $layanan->id,
                    'job_type' => MikrotikJobType::ProvisionPppoe,
                    'status' => MikrotikJobStatus::Failed,
                    'attempt_count' => 1,
                    'error_message' => "Migrasi username gagal: {$e->getMessage()}",
                    'payload' => ['migration' => true, 'old_username' => $oldUsername, 'new_username' => $newUsername],
                    'finished_at' => now(),
                ]);

                $failedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("📋 Preview selesai. {$successCount} layanan akan dimigrasi.");
        } else {
            $this->info("✅ Berhasil dimigrasi  : {$successCount}");

            if ($skippedCount > 0) {
                $this->warn("⏭️  Dilewati (skip)   : {$skippedCount}");
            }

            if ($failedCount > 0) {
                $this->error("❌ Gagal dimigrasi    : {$failedCount}");
                $this->warn('   Periksa MikrotikJobLog untuk detail error per layanan.');
            }
        }

        return self::SUCCESS;
    }
}
