<?php

namespace App\Console\Commands;

use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Jobs\PaymentGateway\ProcessPaymentWebhookJob;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RekonsiliasiPembayaranCommand extends Command
{
    /**
     * Batas waktu sebuah WebhookLog dianggap mandek di status 'diterima' (job tidak
     * kunjung diproses -- worker mati, koneksi queue putus, atau dispatch gagal diam-diam).
     */
    protected const MENIT_AMBANG_MANDEK = 5;

    /**
     * Jendela waktu transaksi Pending yang masih layak dipolling ke gateway.
     * Di luar jendela ini, transaksi dianggap sudah kedaluwarsa secara wajar dan
     * ditangani oleh xendit:cek-va-expired, bukan oleh sweeper ini.
     */
    protected const HARI_JENDELA_PENDING = 7;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pembayaran:rekonsiliasi {--limit=100 : Batas jumlah entri yang ditangani per arah, per eksekusi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sapuan rekonsiliasi dua arah untuk mencegah pembayaran tertahan diam-diam: '.
        'dispatch ulang webhook yang mandek, dan polling status ke gateway untuk transaksi pending yang webhook-nya mungkin hilang';

    /**
     * Execute the console command.
     */
    public function handle(PaymentGatewayManager $manager): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $this->info('Rekonsiliasi Arah A: men-dispatch ulang Log Webhook yang mandek...');
        $jumlahWebhook = $this->rekonsiliasiWebhookMandek($limit);
        $this->info("Selesai. {$jumlahWebhook} Log Webhook di-dispatch ulang.");

        $this->info('Rekonsiliasi Arah B: polling status Transaksi Payment Gateway pending ke gateway...');
        $jumlahTransaksi = $this->rekonsiliasiTransaksiPending($manager, $limit);
        $this->info("Selesai. {$jumlahTransaksi} Transaksi Payment Gateway disinkronkan.");

        return self::SUCCESS;
    }

    /**
     * Arah A -- Job Mandek: dispatch ulang WebhookLog yang mandek di status 'diterima'
     * lebih dari ambang waktu, dan yang berstatus 'gagal' (baik gagal transient setelah
     * retry habis, maupun ditolak karena anomali nominal -- ProcessPaymentWebhookJob aman
     * dijalankan ulang untuk keduanya karena idempoten terhadap status invoice terkini).
     */
    protected function rekonsiliasiWebhookMandek(int $limit): int
    {
        $ambangMandek = now()->subMinutes(self::MENIT_AMBANG_MANDEK);

        $logs = WebhookLog::query()
            ->where(function ($query) use ($ambangMandek) {
                $query->where('status_proses', StatusWebhookLog::Diterima)
                    ->where('diterima_pada', '<', $ambangMandek);
            })
            ->orWhere('status_proses', StatusWebhookLog::Gagal)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ditangani = 0;

        foreach ($logs as $log) {
            try {
                ProcessPaymentWebhookJob::dispatch($log->id);
                $ditangani++;
            } catch (Throwable $e) {
                Log::error("RekonsiliasiPembayaranCommand: gagal men-dispatch ulang WebhookLog ID {$log->id}: ".$e->getMessage());
            }
        }

        return $ditangani;
    }

    /**
     * Arah B -- Webhook Hilang: polling langsung ke gateway untuk transaksi Pending yang
     * invoice-nya belum lunas, dalam jendela waktu wajar. Ini juga yang memungut baris
     * Pending yatim akibat kegagalan panggilan API saat pembuatan payment link (lihat
     * PaymentGatewayManager::buatPaymentLink).
     */
    protected function rekonsiliasiTransaksiPending(PaymentGatewayManager $manager, int $limit): int
    {
        $awalJendela = now()->subDays(self::HARI_JENDELA_PENDING);

        $transaksis = TransaksiPaymentGateway::query()
            ->where('status', StatusTransaksiGateway::Pending)
            ->where('created_at', '>=', $awalJendela)
            ->whereHas('invoice', fn ($query) => $query->where('status', '!=', StatusInvoice::Lunas))
            ->with('invoice')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ditangani = 0;

        foreach ($transaksis as $transaksi) {
            $invoice = $transaksi->invoice;

            if (! $invoice) {
                continue;
            }

            try {
                $manager->sinkronkanStatus($invoice);
                $ditangani++;
            } catch (Throwable $e) {
                Log::error("RekonsiliasiPembayaranCommand: gagal sinkronisasi status untuk Transaksi ID {$transaksi->id} (Invoice {$invoice->no_invoice}): ".$e->getMessage());
            }
        }

        return $ditangani;
    }
}
