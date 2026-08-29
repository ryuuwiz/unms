<?php

namespace App\Services\PaymentGateway;

use App\Contracts\PaymentGateway\PaymentGatewayContract;
use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\MasaAktifSatuan;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\Drivers\IpaymuDriver;
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
        // Daftarkan driver bawaan
        $this->registerDriver('xendit', new XenditDriver);
        $this->registerDriver('ipaymu', new IpaymuDriver);
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

        // 2. Minta driver membuat payment link resmi
        /** @var PaymentLinkResponse $response */
        $response = $driver->createPaymentLink($invoice, $setting);

        // 3. Update record invoice lokal
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

        $fee = $setting->hitungFee('virtual_account', (float) $invoice->jumlah_setelah_promo);

        // 4. Catat transaksi payment gateway
        return TransaksiPaymentGateway::create([
            'invoice_id' => $invoice->id,
            'gateway' => $driver->getProviderName(),
            'external_id' => $response->externalId,
            'xendit_reference_id' => $response->paymentId,
            'channel' => $response->channel,
            'channel_detail' => $response->channelDetail,
            'nomor_pembayaran' => $response->paymentUrl,
            'qr_string' => $response->qrString,
            'total_tagihan' => $response->amount,
            'fee_gateway' => $fee,
            'status' => StatusTransaksiGateway::Pending,
            'expired_at' => $response->expiredAt,
            'payload_request' => [
                'provider' => $driver->getProviderName(),
                'invoice_no' => $invoice->no_invoice,
                'external_id' => $response->externalId,
                'amount' => $response->amount,
            ],
            'payload_response' => $response->rawResponse,
        ]);
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

            $this->prosesPelunasan($invoice, $callbackData, $transaksi);
            $invoice->refresh();
        } elseif ($statusStr === 'EXPIRED' || $statusStr === 'BATAL') {
            if (! $invoice->isLunas()) {
                $invoice->update([
                    'payment_gateway_url' => null,
                    'payment_gateway_status' => 'EXPIRED',
                    'xendit_invoice_url' => null,
                    'xendit_status' => 'EXPIRED',
                ]);
                if ($transaksi && $transaksi->status === StatusTransaksiGateway::Pending) {
                    $transaksi->update(['status' => StatusTransaksiGateway::Expired]);
                }
            }
        }

        return $statusData;
    }

    /**
     * Eksekusi transaksi pelunasan invoice di database dengan row locking dan dispatch event post-commit.
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
            $nominalBayar = $amount > 0 ? $amount : (float) ($transaksi?->total_tagihan ?? $lockedInvoice->jumlah_setelah_promo);
            $providerLabel = strtoupper($provider);
            $refTrx = $paymentRef ?: ($transaksi?->external_id ?? $lockedInvoice->no_invoice);

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

            // 4. Perpanjang Masa Aktif Layanan Pelanggan
            $layanan = $lockedInvoice->layananPelanggan()->lockForUpdate()->first();
            if ($layanan) {
                $paket = $layanan->paketLayanan;
                $masaNilai = $paket ? (int) $paket->masa_aktif_nilai : 1;
                $masaSatuan = $paket ? $paket->masa_aktif_satuan : MasaAktifSatuan::Bulan;

                $promo = $lockedInvoice->promo;
                $bonusBulan = ($promo && $promo->bonus_bulan) ? (int) $promo->bonus_bulan : 0;

                $currentExpired = $layanan->tanggal_expired ? Carbon::parse($layanan->tanggal_expired) : null;
                $baseDate = ($currentExpired && $currentExpired->isFuture())
                    ? $currentExpired->copy()
                    : $dibayarPada->copy()->startOfDay();

                if ($masaSatuan === MasaAktifSatuan::Bulan) {
                    $newExpired = $baseDate->addMonths($masaNilai + $bonusBulan);
                } else {
                    $newExpired = $baseDate->addDays($masaNilai);
                }

                $layanan->update([
                    'tanggal_expired' => $newExpired->toDateString(),
                    'status' => StatusLayanan::Aktif,
                ]);
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
