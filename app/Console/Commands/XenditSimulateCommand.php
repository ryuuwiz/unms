<?php

namespace App\Console\Commands;

use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Models\Invoice;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\Xendit\XenditPaymentService;
use Exception;
use Illuminate\Console\Command;

class XenditSimulateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xendit:simulate 
                            {identifier? : Nomor invoice (misal: INV-202608-000001) atau External ID transaksi}
                            {--local : Eksekusi simulasi webhook langsung di pipeline lokal tanpa butuh tunnel}
                            {--channel=va : Kanal pembayaran jika baru diterbitkan (va atau qris)}
                            {--bank=BCA : Kode bank jika kanal VA (BCA, BNI, BRI, MANDIRI, PERMATA)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mensimulasikan pembayaran tagihan Xendit (Test Mode / Sandbox)';

    /**
     * Execute the console command.
     */
    public function handle(XenditPaymentService $paymentService): int
    {
        $this->info('====================================================');
        $this->info('      XENDIT PAYMENT SIMULATOR (TEST MODE)          ');
        $this->info('====================================================');

        $identifier = $this->argument('identifier');
        $isLocal = (bool) $this->option('local');

        // 1. Cari atau Terbitkan Transaksi Target
        try {
            $transaksi = $this->resolveTransaksi($identifier, $paymentService);
        } catch (Exception $e) {
            $this->error('Gagal menyiapkan transaksi: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $transaksi) {
            $this->error('Tidak ditemukan transaksi gateway atau invoice pending yang siap disimulasikan.');

            return self::FAILURE;
        }

        $invoice = $transaksi->invoice;
        $pelanggan = $invoice->pelanggan;

        $this->table(
            ['Parameter', 'Detail Transaksi'],
            [
                ['No. Invoice', $invoice->no_invoice],
                ['Pelanggan', $pelanggan ? $pelanggan->namaLengkap() : '-'],
                ['External ID', $transaksi->external_id],
                ['Xendit Ref ID', $transaksi->xendit_reference_id ?? '-'],
                ['Channel', strtoupper($transaksi->channel->value).' '.strtoupper($transaksi->channel_detail ?? '')],
                ['Nomor Pembayaran / VA', $transaksi->nomor_pembayaran ?? '-'],
                ['Total Tagihan', 'IDR '.number_format((float) $transaksi->total_tagihan, 0, ',', '.')],
                ['Status Saat Ini', strtoupper($transaksi->status->value)],
                ['Mode Simulasi', $isLocal ? '⚡ Pipeline Webhook Lokal' : '🌐 Remote Xendit Sandbox API'],
            ]
        );

        if (! $this->confirm('Lanjutkan proses simulasi pembayaran ini?', true)) {
            $this->warn('Simulasi dibatalkan.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Memproses simulasi pembayaran...');

        if ($isLocal) {
            $result = $paymentService->simulasikanWebhookLokal($transaksi);

            if ($result['success']) {
                $this->info('✅ Webhook lokal berhasil dieksekusi.');
            } else {
                $this->error('❌ Gagal memproses webhook lokal: '.$result['message']);
                if (isset($result['response'])) {
                    $this->line(json_encode($result['response'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                }

                return self::FAILURE;
            }
        } else {
            $result = $paymentService->simulasikanPembayaran($transaksi);

            if ($result['success']) {
                $this->info('✅ Request simulasi sukses dikirim ke Xendit Sandbox API.');
                $this->line('ℹ️  Xendit akan mengirim webhook callback ke URL yang terdaftar di Dashboard Xendit.');
            } else {
                $this->error('❌ Gagal mengirim simulasi ke Xendit API: '.$result['message']);
                $this->line('💡 Tip: Jika Anda belum menyalakan tunnel webhook publik, gunakan opsi --local:');
                $this->line("   php artisan xendit:simulate {$transaksi->external_id} --local");

                return self::FAILURE;
            }
        }

        // 2. Tampilkan Rekap Status Pasca Simulasi
        $this->newLine();
        $this->info('Hasil Status Setelah Simulasi:');

        $transaksi->refresh();
        $invoice->refresh();
        $layanan = $invoice->layananPelanggan;
        if ($layanan) {
            $layanan->refresh();
        }

        $latestLog = WebhookLog::where('transaksi_payment_gateway_id', $transaksi->id)
            ->orWhere('payload->data->reference_id', $transaksi->external_id)
            ->latest('id')
            ->first();

        $this->table(
            ['Entitas', 'Status', 'Keterangan'],
            [
                ['Transaksi Gateway', strtoupper($transaksi->status->value), 'Status transaksi gateway'],
                ['Invoice', strtoupper($invoice->status->value), 'Tanggal Lunas: '.($invoice->tanggal_lunas ?? '-')],
                ['Layanan Pelanggan', $layanan ? strtoupper($layanan->status->value) : '-', 'Expired Baru: '.($layanan->tanggal_expired ?? '-')],
                ['Log Webhook', $latestLog ? strtoupper($latestLog->status_proses->value) : 'Belum tercatat (async)', 'Event: '.($latestLog->event_type ?? '-')],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Cari transaksi pending atau terbitkan baru dari invoice yang ditentukan.
     */
    protected function resolveTransaksi(?string $identifier, XenditPaymentService $paymentService): ?TransaksiPaymentGateway
    {
        $channel = strtolower((string) $this->option('channel'));
        $bank = strtoupper((string) $this->option('bank'));

        if (! empty($identifier)) {
            // A. Cek jika identifier adalah external_id transaksi gateway
            $transaksi = TransaksiPaymentGateway::where('external_id', $identifier)->first();
            if ($transaksi) {
                return $transaksi;
            }

            // B. Cek jika identifier adalah nomor invoice atau ID invoice
            $invoice = Invoice::where('no_invoice', $identifier)
                ->orWhere('id', is_numeric($identifier) ? $identifier : 0)
                ->first();

            if ($invoice) {
                if ($invoice->isLunas()) {
                    $this->warn("Invoice [{$invoice->no_invoice}] sudah berstatus Lunas.");

                    return null;
                }

                // Cek apakah invoice sudah memiliki transaksi pending yang belum expired
                $existingTrx = TransaksiPaymentGateway::where('invoice_id', $invoice->id)
                    ->where('status', StatusTransaksiGateway::Pending)
                    ->where('expired_at', '>', now())
                    ->latest('id')
                    ->first();

                if ($existingTrx) {
                    return $existingTrx;
                }

                // Jika belum ada transaksi aktif, otomatis terbitkan transaksi baru
                $this->info("ℹ️  Menerbitkan kode tagihan baru untuk invoice {$invoice->no_invoice}...");
                if ($channel === 'qris') {
                    return $paymentService->buatQris($invoice);
                }

                return $paymentService->buatVirtualAccount($invoice, $bank);
            }

            return null;
        }

        // C. Jika tidak ada identifier, cari transaksi pending yang ada
        $pendingTransactions = TransaksiPaymentGateway::with(['invoice.pelanggan'])
            ->where('status', StatusTransaksiGateway::Pending)
            ->where('expired_at', '>', now())
            ->latest('id')
            ->take(10)
            ->get();

        if ($pendingTransactions->isNotEmpty()) {
            if ($pendingTransactions->count() === 1) {
                return $pendingTransactions->first();
            }

            $options = [];
            foreach ($pendingTransactions as $trx) {
                $inv = $trx->invoice;
                $pelangganName = $inv?->pelanggan?->namaLengkap() ?? '-';
                $options['trx_'.$trx->id] = sprintf(
                    '[Transaksi %s] Invoice %s (%s) - Rp %s',
                    $trx->external_id,
                    $inv->no_invoice ?? '-',
                    $pelangganName,
                    number_format((float) $trx->total_tagihan, 0, ',', '.')
                );
            }

            $selectedKey = $this->choice('Pilih transaksi gateway pending yang ingin disimulasikan:', $options);
            $selectedId = (int) str_replace('trx_', '', array_search($selectedKey, $options, true) ?: (string) array_key_first($options));

            return TransaksiPaymentGateway::find($selectedId);
        }

        // D. Jika tidak ada transaksi gateway pending, cari Invoice yang belum dibayar
        $pendingInvoices = Invoice::with('pelanggan')
            ->where('status', StatusInvoice::MenungguPembayaran)
            ->latest('id')
            ->take(10)
            ->get();

        if ($pendingInvoices->isEmpty()) {
            return null;
        }

        $invOptions = [];
        foreach ($pendingInvoices as $inv) {
            $pelangganName = $inv->pelanggan?->namaLengkap() ?? '-';
            $invOptions['inv_'.$inv->id] = sprintf(
                '[Invoice %s] %s - Rp %s',
                $inv->no_invoice,
                $pelangganName,
                number_format((float) $inv->jumlah_setelah_promo, 0, ',', '.')
            );
        }

        $selectedInvKey = $this->choice('Tidak ada transaksi gateway aktif. Pilih Invoice pending untuk diterbitkan tagihannya:', $invOptions);
        $selectedInvId = (int) str_replace('inv_', '', array_search($selectedInvKey, $invOptions, true) ?: (string) array_key_first($invOptions));

        $selectedInvoice = Invoice::findOrFail($selectedInvId);

        $this->info("ℹ️  Menerbitkan tagihan baru untuk invoice {$selectedInvoice->no_invoice}...");
        if ($channel === 'qris') {
            return $paymentService->buatQris($selectedInvoice);
        }

        return $paymentService->buatVirtualAccount($selectedInvoice, $bank);
    }
}
