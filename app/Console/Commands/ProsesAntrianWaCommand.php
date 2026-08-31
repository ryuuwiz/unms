<?php

namespace App\Console\Commands;

use App\Enums\Wa\StatusAntrianWa;
use App\Models\AntrianWaBlast;
use App\Models\Sysblas;
use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProsesAntrianWaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wa:proses-antrian {--limit=100 : Jumlah maksimal antrean yang diproses per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proses dan dispatch antrean pesan WhatsApp yang siap dikirim';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Memeriksa antrean WhatsApp siap kirim (Limit: {$limit})...");

        $antreanList = AntrianWaBlast::query()
            ->with('sysblas')
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

        // Kelompokkan berdasarkan koneksi Sysblas
        $grouped = $antreanList->groupBy(fn ($item) => $item->sysblas_id ?? 0);
        $totalTerkirim = 0;
        $totalGagal = 0;

        foreach ($grouped as $sysblasId => $items) {
            $sysblas = $items->first()->sysblas ?? Sysblas::getDefault();
            $client = $sysblas ? $sysblas->makeClient() : app(WhatsappClient::class);

            // Chunk per 50 pesan untuk pengiriman batch API WABLAS v2
            foreach ($items->chunk(50) as $chunk) {
                $batchPayload = [];
                foreach ($chunk as $antrian) {
                    $batchPayload[] = [
                        'phone' => $antrian->no_hp_tujuan,
                        'message' => $antrian->pesan,
                    ];
                }

                $delay = $sysblas ? ($sysblas->delay_detik ?? 3) : 3;
                $jitter = $sysblas ? ($sysblas->jitter_detik ?? 2) : 2;
                $result = $client->sendBatchMessages($batchPayload, $delay, $jitter);

                foreach ($chunk as $antrian) {
                    if ($result['success']) {
                        $antrian->tandaiTerkirim($result);
                        $totalTerkirim++;
                    } else {
                        $antrian->tandaiGagal($result['message'], $result);
                        $totalGagal++;
                    }
                }
            }
        }

        $this->info("Pemrosesan antrean selesai: {$totalTerkirim} terkirim, {$totalGagal} gagal.");

        return Command::SUCCESS;
    }
}
