<?php

namespace App\Console\Commands;

use App\Events\StatusSesiPppBerubah;
use App\Models\LayananPelanggan;
use Illuminate\Console\Command;

class MikrotikBroadcastDemoCommand extends Command
{
    protected $signature = 'mikrotik:broadcast-demo {pelanggan : ID Pelanggan}';

    protected $description = 'Siarkan event StatusSesiPppBerubah palsu untuk layanan pertama seorang pelanggan (uji jalur Reverb tanpa router)';

    public function handle(): int
    {
        $layanan = LayananPelanggan::where('pelanggan_id', $this->argument('pelanggan'))->first();

        if (! $layanan) {
            $this->error('Pelanggan tidak punya layanan.');

            return self::FAILURE;
        }

        StatusSesiPppBerubah::dispatch($layanan->pelanggan_id, $layanan->id);

        $this->info("Event disiarkan ke pelanggan.{$layanan->pelanggan_id} untuk layanan #{$layanan->id}.");

        return self::SUCCESS;
    }
}
