<?php

namespace App\Console\Commands;

use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use App\Models\PengaturanSiklusTagihan;
use App\Services\Billing\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:generate {--force : Terbitkan invoice tanpa memeriksa Hari Terbit Invoice}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis menerbitkan invoice siklus berikutnya untuk layanan aktif/suspend pada Hari Terbit Invoice (Siklus Tagihan)';

    /**
     * Execute the console command.
     *
     * Satu invoice per layanan per eksekusi: layanan yang tertinggal beberapa siklus terkejar satu
     * siklus per hari, dan tiap invoice baru menyerap tunggakan sebelumnya.
     */
    public function handle(BillingService $billingService): int
    {
        $this->info('Memulai pengecekan layanan aktif/suspend untuk penerbitan invoice...');

        $siklus = PengaturanSiklusTagihan::ambil();
        $hariIni = Carbon::today();
        $force = (bool) $this->option('force');
        $count = 0;

        LayananPelanggan::query()
            ->with('paketLayanan')
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend])
            ->whereNotNull('tanggal_expired')
            ->chunkById(200, function ($layanans) use ($billingService, $siklus, $hariIni, $force, &$count) {
                foreach ($layanans as $layanan) {
                    $rencana = $billingService->rencanaSiklusBerikutnya($layanan, $siklus);

                    if (! $force && $rencana['terbit']->gt($hariIni)) {
                        continue;
                    }

                    $invoice = $billingService->generateInvoice(
                        layanan: $layanan,
                        tanggalJatuhTempo: $rencana['jatuh_tempo'],
                        periodeTagihan: $rencana['periode'],
                    );

                    if ($invoice->wasRecentlyCreated) {
                        $count++;
                    }
                }
            });

        $this->info("Berhasil menerbitkan {$count} invoice baru.");

        return Command::SUCCESS;
    }
}
