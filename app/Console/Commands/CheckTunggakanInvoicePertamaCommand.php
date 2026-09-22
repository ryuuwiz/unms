<?php

namespace App\Console\Commands;

use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckTunggakanInvoicePertamaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'layanan:cek-tunggakan-pertama';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Isolir layanan yang belum pernah membayar invoice pertama (instalasi) melewati Tenggat Pembayaran Invoice Pertama (H+1)';

    /**
     * Execute the console command.
     *
     * Hanya menyasar layanan yang masih di siklus pertama (belum pernah punya invoice
     * periodik/bulanan) -- lihat CONTEXT.md "Tenggat Pembayaran Invoice Pertama". Layanan
     * yang sudah pernah lunas invoice pertamanya dan lanjut ke tagihan bulanan ditangani
     * oleh layanan:cek-isolir (berbasis tanggal_expired), bukan command ini.
     */
    public function handle(UbahStatusLayananAction $ubahStatusAction): int
    {
        $this->info('Memeriksa layanan dengan invoice pertama yang belum dibayar melewati tenggat...');

        $today = Carbon::today();

        $layanans = LayananPelanggan::query()
            ->where('status', StatusLayanan::Aktif)
            ->whereDoesntHave('invoices', fn ($q) => $q->whereNotNull('periode_tagihan'))
            ->whereHas('invoices', fn ($q) => $q
                ->whereNull('periode_tagihan')
                ->whereIn('status', StatusInvoice::terbuka())
                ->where('tanggal_jatuh_tempo', '<', $today)
            )
            ->get();

        $count = 0;
        foreach ($layanans as $layanan) {
            $ubahStatusAction->execute(
                layanan: $layanan,
                statusBaru: StatusLayanan::Suspend,
                actor: null,
                catatan: 'Isolir otomatis sistem UNMS: invoice pertama belum dibayar melewati Tenggat Pembayaran Invoice Pertama (H+1)'
            );
            $count++;
        }

        $this->info("Berhasil mengisolir {$count} layanan yang menunggak invoice pertama.");

        return Command::SUCCESS;
    }
}
