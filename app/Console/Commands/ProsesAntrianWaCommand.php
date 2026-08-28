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

        $count = 0;
        foreach ($antreanList as $antrian) {
            KirimWaBlastJob::dispatch($antrian);
            $count++;
        }

        $this->info("Berhasil men-dispatch {$count} antrean WhatsApp ke queue worker.");

        return Command::SUCCESS;
    }
}
