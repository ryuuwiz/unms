<?php

namespace App\Console\Commands;

use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProsesAntrianWaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wa:proses-antrian {--limit=100 : Jumlah maksimal antrean yang di-dispatch ulang per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sapu antrean pesan WhatsApp yang masih menunggu (macet) dan dispatch ulang ke job pengiriman ber-rate-limit';

    /**
     * Execute the console command.
     *
     * Command ini adalah Penyapu Antrean Macet: tidak pernah mengirim pesan WhatsApp secara
     * langsung. Ia hanya men-dispatch ulang baris antrian blast yang tertinggal ke
     * `KirimWaBlastJob`, agar satu-satunya jalur pengiriman nyata tetap menghormati Batas Laju
     * Pengiriman & Jeda Antar-Pesan per gateway (lihat ADR 0035).
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Memeriksa antrean WhatsApp yang macet (Limit: {$limit})...");

        $antreanList = AntrianWaBlast::query()
            ->where('status', StatusAntrianWa::Menunggu)
            ->where(function ($q) {
                $q->whereNull('dijadwalkan_pada')
                    ->orWhere('dijadwalkan_pada', '<=', Carbon::now());
            })
            ->limit($limit)
            ->get();

        if ($antreanList->isEmpty()) {
            $this->info('Tidak ada antrean WhatsApp yang tertunda.');

            return Command::SUCCESS;
        }

        foreach ($antreanList as $antrian) {
            KirimWaBlastJob::dispatch($antrian);
        }

        $this->info("Pemrosesan antrean selesai: {$antreanList->count()} pesan di-dispatch ulang ke antrean pengiriman.");

        return Command::SUCCESS;
    }
}
