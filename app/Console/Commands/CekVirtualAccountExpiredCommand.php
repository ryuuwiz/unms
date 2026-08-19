<?php

namespace App\Console\Commands;

use App\Enums\StatusTransaksiGateway;
use App\Models\TransaksiPaymentGateway;
use Illuminate\Console\Command;

class CekVirtualAccountExpiredCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xendit:cek-va-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa dan menandai transaksi payment gateway pending yang telah melewati batas kedaluwarsa';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memeriksa transaksi payment gateway yang kedaluwarsa...');

        $expiredCount = TransaksiPaymentGateway::where('status', StatusTransaksiGateway::Pending)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->update([
                'status' => StatusTransaksiGateway::Expired,
            ]);

        $this->info("Selesai. Total {$expiredCount} transaksi ditandai kedaluwarsa.");

        return self::SUCCESS;
    }
}
