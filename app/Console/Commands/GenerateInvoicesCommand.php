<?php

namespace App\Console\Commands;

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
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
    protected $signature = 'invoice:generate {--force : Terbitkan invoice tanpa memeriksa rentang H-7}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis menerbitkan invoice tagihan baru untuk layanan pelanggan aktif menjelang expired (H-7)';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService): int
    {
        $this->info('Memulai pengecekan layanan aktif untuk penerbitan invoice...');

        $thresholdDate = Carbon::today()->addDays(7);
        $force = (bool) $this->option('force');

        $query = LayananPelanggan::query()
            ->with(['pelanggan', 'paketLayanan'])
            ->where('status', StatusLayanan::Aktif);

        if (! $force) {
            $query->where('tanggal_expired', '<=', $thresholdDate);
        }

        $layanans = $query->get();
        $count = 0;

        foreach ($layanans as $layanan) {
            $targetPeriode = $layanan->getNextPeriodeTagihan();

            // 1. Cek apakah sudah ada invoice aktif/lunas/kadaluarsa untuk target periode ini
            $hasExistingForPeriod = $layanan->invoices()
                ->where('periode_tagihan', $targetPeriode)
                ->where('status', '!=', StatusInvoice::Dibatalkan)
                ->exists();

            if ($hasExistingForPeriod) {
                continue;
            }

            // 2. Cek apakah masih ada invoice berstatus menunggu pembayaran dari periode sebelumnya
            $hasPendingInvoice = $layanan->invoices()
                ->where('status', StatusInvoice::MenungguPembayaran)
                ->exists();

            if ($hasPendingInvoice) {
                continue;
            }

            $billingService->generateInvoice(
                layanan: $layanan,
                periodeTagihan: $targetPeriode
            );
            $count++;
        }

        $this->info("Berhasil menerbitkan {$count} invoice baru.");

        return Command::SUCCESS;
    }
}
