<?php

namespace App\Console\Commands;

use App\Enums\StatusTransaksiGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CekVirtualAccountExpiredCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xendit:cek-va-expired {--limit=200 : Batas jumlah transaksi yang diperiksa per eksekusi}';

    protected const HARI_JENDELA = 30;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menanyakan ke gateway status transaksi pending yang sudah melewati batas waktu, lalu menerapkan statusnya';

    /**
     * Transaksi baru Kedaluwarsa bila gateway sendiri menyatakannya (ADR-0067). Menandai
     * Expired hanya karena waktu lokal lewat membuat pembayaran yang sebenarnya lunas --
     * tetapi callback-nya tertolak -- tidak pernah dipulihkan, sebab rekonsiliasi hanya
     * memoles transaksi Pending.
     */
    public function handle(PaymentGatewayManager $manager): int
    {
        $this->info('Memeriksa transaksi payment gateway yang melewati batas waktu ke gateway...');

        $transaksis = TransaksiPaymentGateway::query()
            ->where('status', StatusTransaksiGateway::Pending)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            // Jendela agar transaksi yang terus gagal dicek tidak menghabiskan kuota tiap jam;
            // yang lebih lama ditangani manual lewat `pembayaran:pulihkan`.
            ->where('expired_at', '>=', now()->subDays(self::HARI_JENDELA))
            ->with(['invoice', 'pengaturanGateway'])
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $kedaluwarsa = 0;

        foreach ($transaksis as $transaksi) {
            try {
                $manager->sinkronkanTransaksi($transaksi);
            } catch (Throwable $e) {
                Log::error("xendit:cek-va-expired: gagal memeriksa Transaksi ID {$transaksi->id}: ".$e->getMessage());

                continue;
            }

            if ($transaksi->fresh()?->status === StatusTransaksiGateway::Expired) {
                $kedaluwarsa++;
            }
        }

        $this->info("Selesai. {$transaksis->count()} transaksi diperiksa, {$kedaluwarsa} dinyatakan kedaluwarsa oleh gateway.");

        return self::SUCCESS;
    }
}
