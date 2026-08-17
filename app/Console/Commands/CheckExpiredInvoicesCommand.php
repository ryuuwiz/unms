<?php

namespace App\Console\Commands;

use App\Enums\StatusInvoice;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckExpiredInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:cek-kadaluarsa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pemeriksaan harian untuk menandai invoice yang melewati tanggal jatuh tempo sebagai kadaluarsa';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memeriksa invoice yang melewati tanggal jatuh tempo...');

        $today = Carbon::today();

        $affected = Invoice::query()
            ->where('status', StatusInvoice::MenungguPembayaran)
            ->where('tanggal_jatuh_tempo', '<', $today)
            ->update([
                'status' => StatusInvoice::Kadaluarsa,
            ]);

        $this->info("Berhasil memperbarui {$affected} invoice menjadi kadaluarsa.");

        return Command::SUCCESS;
    }
}
