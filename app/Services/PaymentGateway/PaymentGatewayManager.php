<?php

namespace App\Services\PaymentGateway;

use App\Actions\LayananPelanggan\PerpanjangMasaAktifAction;
use App\Contracts\PaymentGateway\PaymentGatewayContract;
use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\GatewayChannel;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayContract>
     */
    protected array $drivers = [];

    public function __construct()
    {
        // Daftarkan driver bawaan.
        // IpaymuDriver sengaja TIDAK didaftarkan: verifikasi signature-nya belum
        // diimplementasikan, sehingga mendaftarkannya akan membuka endpoint webhook publik
        // yang dapat dipalsukan. Daftarkan kembali hanya setelah verifikasi HMAC iPaymu selesai.
        $this->registerDriver('xendit', new XenditDriver);
    }

    /**
     * Daftarkan driver baru ke manager.
     */
    public function registerDriver(string $name, PaymentGatewayContract $driver): void
    {
        $this->drivers[strtolower($name)] = $driver;
    }

    /**
     * Dapatkan daftar provider yang didukung sistem.
     *
     * @return array<string, string>
     */
    public function getSupportedProviders(): array
    {
        $list = [];
        foreach ($this->drivers as $name => $driver) {
            $list[$name] = $driver->getProviderLabel();
        }

        return $list;
    }

    /**
     * Resolve instance driver.
     */
    public function driver(?string $provider = null): PaymentGatewayContract
    {
        if (empty($provider)) {
            $setting = PengaturanGateway::getDefault();
            $provider = $setting ? $setting->provider : 'xendit';
        }

        $normalized = strtolower(trim($provider));

        if (! isset($this->drivers[$normalized])) {
            throw new InvalidArgumentException("Payment gateway driver [{$provider}] tidak ditemukan atau belum didukung.");
        }

        return $this->drivers[$normalized];
    }

    /**
     * Dapatkan konfigurasi setting untuk provider atau default setting.
     */
    public function getSetting(?string $provider = null): PengaturanGateway
    {
        if (! empty($provider)) {
            $setting = PengaturanGateway::getSettingForProvider($provider);
            if ($setting) {
                return $setting;
            }
        }

        $default = PengaturanGateway::getDefault();
        if ($default) {
            return $default;
        }

        return PengaturanGateway::getXenditSetting();
    }

    /**
     * Terbitkan link pembayaran gateway resmi untuk invoice.
     *
     * Urutan sengaja: reservasi baris TransaksiPaymentGateway lokal DULU (dengan
     * external_id dan total_tagihan final, termasuk fee) sebelum memanggil API gateway.
     * Jika panggilan API gagal/timeout, baris Pending ini tertinggal sebagai jejak yang
     * dapat direkonsiliasi (lihat RekonsiliasiPembayaranCommand). Jika panggilan API
     * berhasil tapi update baris ini setelahnya gagal, invoice yang sudah punya link
     * pembayaran tetap tertaut ke baris transaksi dengan nominal yang benar (termasuk
     * fee) -- webhook nantinya tetap menemukan baris ini via external_id, sehingga tidak
     * lagi jatuh ke pembanding jumlah_setelah_promo tanpa fee yang memicu anomali palsu.
     */
    public function buatPaymentLink(
        Invoice $invoice,
        ?string $provider = null,
        bool $forceRegenerate = false
    ): TransaksiPaymentGateway {
        // 1. Cek apakah invoice sudah memiliki link aktif yang belum kedaluwarsa
        if (! $forceRegenerate && $invoice->hasActivePaymentLink()) {
            $existingTrx = TransaksiPaymentGateway::where('invoice_id', $invoice->id)
                ->where('status', StatusTransaksiGateway::Pending)
                ->where('expired_at', '>', now())
                ->latest('id')
                ->first();

            if ($existingTrx) {
                return $existingTrx;
            }
        }

        $setting = $this->getSetting($provider);
        $driver = $this->driver($setting->provider);

        // 2. Reservasi baris transaksi lokal SEBELUM memanggil API gateway.
        // fee dihitung dengan formula yang identik dengan yang dipakai driver saat
        // menyusun payload 'amount' ke gateway (lihat XenditDriver::createPaymentLink),
        // sehingga total_tagihan di sini sudah pasti sama dengan paid_amount yang akan
        // dilaporkan webhook.
        $externalId = $driver->generateExternalId($invoice);
        $fee = $setting->hitungFee('virtual_account', (float) $invoice->jumlah_setelah_promo);
        $totalTagihanEstimasi = (float) $invoice->jumlah_setelah_promo + $fee;

        $transaksi = TransaksiPaymentGateway::create([
            'invoice_id' => $invoice->id,
            'gateway' => $driver->getProviderName(),
            'external_id' => $externalId,
            'channel' => GatewayChannel::Invoice,
            'total_tagihan' => $totalTagihanEstimasi,
            'fee_gateway' => $fee,
            'status' => StatusTransaksiGateway::Pending,
            'payload_request' => [
                'provider' => $driver->getProviderName(),
                'invoice_no' => $invoice->no_invoice,
                'external_id' => $externalId,
                'amount' => $totalTagihanEstimasi,
            ],
        ]);

        // 3. Minta driver membuat payment link resmi menggunakan external_id yang sudah direservasi
        /** @var PaymentLinkResponse $response */
        $response = $driver->createPaymentLink($invoice, $setting, $externalId);

        // 4. Update invoice lokal & baris transaksi hasil reservasi dalam satu transaksi DB
        return DB::transaction(function () use ($invoice, $driver, $response, $transaksi) {
            $invoice->update([
                'payment_gateway_url' => $response->paymentUrl,
                'payment_gateway_id' => $response->paymentId,
                'payment_gateway_provider' => $driver->getProviderName(),
                'payment_gateway_status' => 'PENDING',
                'payment_gateway_expired_at' => $response->expiredAt,
                // Kompatibilitas mundur
                'xendit_invoice_id' => $response->paymentId,
                'xendit_invoice_url' => $response->paymentUrl,
                'xendit_status' => 'PENDING',
                'xendit_expired_at' => $response->expiredAt,
            ]);

            $transaksi->update([
                'xendit_reference_id' => $response->paymentId,
                'channel' => $response->channel,
                'channel_detail' => $response->channelDetail,
                'nomor_pembayaran' => $response->paymentUrl,
                'qr_string' => $response->qrString,
                'total_tagihan' => $response->amount,
                'expired_at' => $response->expiredAt,
                'payload_response' => $response->rawResponse,
            ]);

            return $transaksi;
        });
    }

    /**
     * Sinkronkan status lalu pastikan invoice punya tautan pembayaran aktif, kembalikan URL
     * hosted payment page resmi -- null jika invoice sudah lunas atau tautan gagal diterbitkan.
     * Titik reuse tunggal untuk semua tombol "Bayar Sekarang" (portal login maupun tautan publik).
     */
    public function resolvePaymentUrl(Invoice $invoice): ?string
    {
        $invoice->refresh();

        if ($invoice->isLunas()) {
            return null;
        }

        if (! empty($invoice->payment_gateway_id) || ! empty($invoice->xendit_invoice_id)) {
            $this->sinkronkanStatus($invoice);
            $invoice->refresh();

            if ($invoice->isLunas()) {
                return null;
            }
        }

        if (! $invoice->hasActivePaymentLink()) {
            $this->buatPaymentLink($invoice, forceRegenerate: true);
            $invoice->refresh();
        }

        return $invoice->payment_gateway_url ?: ($invoice->xendit_invoice_url ?: null);
    }

    /**
     * Sinkronisasikan status invoice langsung ke API gateway.
     *
     * @return array<string, mixed>
     */
    public function sinkronkanStatus(Invoice $invoice): array
    {
        $provider = $invoice->payment_gateway_provider ?: 'xendit';
        $setting = $this->getSetting($provider);
        $driver = $this->driver($provider);

        $statusData = $driver->checkStatus($invoice, $setting);
        $statusStr = strtoupper((string) ($statusData['status'] ?? ''));

        $transaksi = $invoice->transaksiPaymentGatewayAktif();
        if ($transaksi && ! empty($statusData) && ! isset($statusData['error'])) {
            $transaksi->update([
                'payload_response' => $statusData,
                'provider_reference_id' => $statusData['id'] ?? $transaksi->provider_reference_id,
                'xendit_reference_id' => $statusData['id'] ?? $transaksi->xendit_reference_id,
            ]);

            // Catat log audit sinkronisasi API jika belum ada
            $eventId = (string) ($statusData['id'] ?? ($transaksi->provider_reference_id ?: $transaksi->external_id));
            if (! empty($eventId)) {
                WebhookLog::firstOrCreate(
                    [
                        'provider' => $provider,
                        'provider_event_id' => $eventId,
                    ],
                    [
                        'transaksi_payment_gateway_id' => $transaksi->id,
                        'event_type' => "sync.{$provider}",
                        'xendit_event_id' => $eventId,
                        'payload' => $statusData,
                        'status_proses' => in_array($statusStr, ['PAID', 'SETTLED', 'SUCCEEDED', 'BERHASIL', 'EXPIRED'], true) ? StatusWebhookLog::Diproses : StatusWebhookLog::Diterima,
                        'diterima_pada' => Carbon::now(),
                    ]
                );
            }
        }

        if (in_array($statusStr, ['PAID', 'SETTLED', 'SUCCEEDED', 'BERHASIL'], true)) {
            $callbackData = new PaymentCallbackData(
                provider: $provider,
                externalId: $transaksi?->external_id ?: $invoice->no_invoice,
                status: 'PAID',
                paidAmount: (float) ($statusData['paid_amount'] ?? ($statusData['amount'] ?? $invoice->jumlah_setelah_promo)),
                eventId: (string) ($statusData['id'] ?? null),
                paidAt: now()->toIso8601String(),
                rawPayload: $statusData
            );

            // prosesPelunasan() sudah mengunci baris invoice & mengecek ulang isLunas() di
            // dalam transaksinya sendiri, jadi aman dipanggil langsung dari sini.
            $this->prosesPelunasan($invoice, $callbackData, $transaksi);
            $invoice->refresh();
        } elseif ($statusStr === 'EXPIRED' || $statusStr === 'BATAL') {
            // Dikunci + re-check isLunas() DI DALAM lock: dipanggil berulang oleh sweeper
            // terjadwal (lihat RekonsiliasiPembayaranCommand), sehingga rawan berbenturan
            // dengan webhook yang baru saja melunasi invoice yang sama secara paralel.
            DB::transaction(function () use ($invoice, $transaksi) {
                /** @var Invoice $lockedInvoice */
                $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

                if (! $lockedInvoice->isLunas()) {
                    $lockedInvoice->update([
                        'payment_gateway_url' => null,
                        'payment_gateway_status' => 'EXPIRED',
                        'xendit_invoice_url' => null,
                        'xendit_status' => 'EXPIRED',
                    ]);

                    if ($transaksi && $transaksi->status === StatusTransaksiGateway::Pending) {
                        $transaksi->update(['status' => StatusTransaksiGateway::Expired]);
                    }
                }
            });
        }

        return $statusData;
    }

    /**
     * Eksekusi transaksi pelunasan invoice di database dengan row locking dan dispatch event post-commit.
     *
     * @param  PaymentCallbackData|array<string, mixed>  $payloadOrData
     */
    public function prosesPelunasan(
        Invoice $invoice,
        PaymentCallbackData|array $payloadOrData,
        ?TransaksiPaymentGateway $transaksi = null,
        ?WebhookLog $webhookLog = null
    ): bool {
        $eventToDispatch = null;

        if ($payloadOrData instanceof PaymentCallbackData) {
            $callbackData = $payloadOrData;
            $rawPayload = $callbackData->rawPayload;
            $amount = $callbackData->paidAmount;
            $paidAtStr = $callbackData->paidAt;
            $channel = $callbackData->channel->value;
            $channelDetail = $callbackData->channelDetail;
            $paymentRef = $callbackData->paymentReference;
            $provider = $callbackData->provider;
        } else {
            $rawPayload = $payloadOrData;
            $provider = (string) ($rawPayload['provider'] ?? 'xendit');
            $amount = (float) ($rawPayload['paid_amount'] ?? ($rawPayload['amount'] ?? $invoice->jumlah_setelah_promo));
            $paidAtStr = (string) ($rawPayload['paid_at'] ?? now()->toIso8601String());
            $channel = (string) ($rawPayload['channel'] ?? 'invoice');
            $channelDetail = (string) ($rawPayload['channel_detail'] ?? null);
            $paymentRef = (string) ($rawPayload['payment_id'] ?? ($rawPayload['id'] ?? null));
        }

        if (! $transaksi) {
            $transaksi = $invoice->transaksiPaymentGatewayAktif();
        }

        DB::transaction(function () use ($invoice, $transaksi, $webhookLog, $rawPayload, $amount, $paidAtStr, $channel, $channelDetail, $paymentRef, $provider, &$eventToDispatch) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            // Guard clause idempotensi: Jika invoice sudah lunas sebelumnya
            if ($lockedInvoice->isLunas()) {
                if ($transaksi) {
                    $transaksi->update([
                        'status' => StatusTransaksiGateway::Paid,
                        'payload_response' => $rawPayload,
                    ]);
                }
                $lockedInvoice->update([
                    'payment_gateway_status' => 'PAID',
                    'xendit_status' => 'PAID',
                ]);
                if ($webhookLog) {
                    $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);
                }

                return;
            }

            $dibayarPada = $paidAtStr ? Carbon::parse($paidAtStr) : Carbon::now();

            // 1. Update Transaksi Payment Gateway
            if ($transaksi) {
                $transaksi->update([
                    'status' => StatusTransaksiGateway::Paid,
                    'provider_reference_id' => $paymentRef ?: $transaksi->provider_reference_id,
                    'xendit_reference_id' => $paymentRef ?: $transaksi->xendit_reference_id,
                    'payload_response' => $rawPayload,
                ]);
            }

            // 2. Buat / Dapatkan Record Pembayaran secara idempoten
            $channelDetailText = $channelDetail ? strtoupper($channelDetail) : '';
            $nominalBayar = $amount > 0 ? $amount : (float) ($transaksi->total_tagihan ?? $lockedInvoice->jumlah_setelah_promo);
            $providerLabel = strtoupper($provider);
            $refTrx = $paymentRef ?: ($transaksi->external_id ?? $lockedInvoice->no_invoice);

            $pembayaran = Pembayaran::firstOrCreate(
                [
                    'metode' => MetodePembayaran::PaymentGateway,
                    'referensi_transaksi' => $refTrx,
                ],
                [
                    'invoice_id' => $lockedInvoice->id,
                    'jumlah_dibayar' => $nominalBayar,
                    'dibayar_pada' => $dibayarPada,
                    'catatan' => trim("Pembayaran otomatis {$providerLabel} {$channel} {$channelDetailText}"),
                ]
            );

            // 3. Update Status Invoice
            $lockedInvoice->update([
                'status' => StatusInvoice::Lunas,
                'tanggal_lunas' => $dibayarPada->toDateString(),
                'metode_pembayaran' => MetodePembayaran::PaymentGateway,
                'payment_gateway_status' => 'PAID',
                'xendit_status' => 'PAID',
            ]);

            // 4. Perpanjang Masa Aktif Layanan Pelanggan -- aturan tunggal isFuture()
            // yang sama dengan jalur pembayaran manual, lihat PerpanjangMasaAktifAction.
            $layanan = $lockedInvoice->layananPelanggan()->lockForUpdate()->first();
            if ($layanan) {
                app(PerpanjangMasaAktifAction::class)->execute($layanan, $lockedInvoice, $dibayarPada);
            }

            // 5. Update Status Log Webhook
            if ($webhookLog) {
                $webhookLog->update(['status_proses' => StatusWebhookLog::Diproses]);
            }

            // Siapkan event untuk dipancarkan setelah commit DB
            $eventToDispatch = new InvoicePaidEvent($lockedInvoice, $pembayaran);
        });

        if ($eventToDispatch) {
            event($eventToDispatch);
        }

        return true;
    }

    /**
     * Cek koneksi API gateway.
     */
    public function pingConnection(PengaturanGateway $setting): PingConnectionResult
    {
        $driver = $this->driver($setting->provider);

        return $driver->pingConnection($setting);
    }
}
