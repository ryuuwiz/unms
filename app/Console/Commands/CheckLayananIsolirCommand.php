<?php

namespace App\Console\Commands;

use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckLayananIsolirCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'layanan:cek-isolir';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pemeriksaan harian untuk mengisolir (suspend) layanan pelanggan yang telah melewati batas tanggal expired';

    /**
     * Execute the console command.
     */
    public function handle(UbahStatusLayananAction $ubahStatusAction): int
    {
        $this->info('Memeriksa layanan pelanggan yang telah melewati batas tanggal expired...');

        $today = Carbon::today();

        $layanans = LayananPelanggan::query()
            ->where('status', StatusLayanan::Aktif)
            ->where('tanggal_expired', '<', $today)
            ->get();

        $count = 0;
        foreach ($layanans as $layanan) {
            $ubahStatusAction->execute(
                layanan: $layanan,
                statusBaru: StatusLayanan::Suspend,
                actor: null,
                catatan: 'Isolir otomatis harian sistem UNMS: masa aktif telah berakhir'
            );
            $count++;
        }

        $this->info("Berhasil mengisolir {$count} layanan pelanggan yang menunggak.");

        return Command::SUCCESS;
    }
}
