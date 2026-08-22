<?php

namespace App\Services\Xendit;

use App\DTO\Xendit\XenditCallbackData;
use App\Enums\GatewayChannel;
use App\Enums\MasaAktifSatuan;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Http\Controllers\Webhook\XenditWebhookController;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Xendit\BalanceAndTransaction\BalanceApi;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\CustomerObject;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\InvoiceFee;
use Xendit\Invoice\InvoiceItem;
use Xendit\XenditSdkException;

class XenditPaymentService
{
    protected InvoiceApi $invoiceApi;

    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.xendit.secret_key');
        Configuration::setXenditKey($this->apiKey);
        $this->invoiceApi = new InvoiceApi(
            client: new Client(['timeout' => 30]),
            config: Configuration::getDefaultConfiguration()
        );
    }

    /**
     * Terbitkan link pembayaran Xendit Hosted Invoice untuk tagihan tertentu.
     */
    public function buatInvoice(Invoice $invoice, bool $forceRegenerate = false): TransaksiPaymentGateway
    {
        // 1. Cek apakah invoice sudah punya transaksi aktif & belum kedaluwarsa
        if (! $forceRegenerate && $invoice->hasActiveXenditInvoice()) {
            $existingTrx = TransaksiPaymentGateway::where('invoice_id', $invoice->id)
                ->where('status', StatusTransaksiGateway::Pending)
                ->where('expired_at', '>', now())
                ->latest('id')
                ->first();

            if ($existingTrx) {
                return $existingTrx;
            }
        }

        $pengaturan = PengaturanGateway::getXenditSetting();
        $nominalInvoice = (float) $invoice->jumlah_setelah_promo;
        $fee = $pengaturan->hitungFee('virtual_account', $nominalInvoice);
        $totalTagihan = $nominalInvoice + $fee;

        $invoiceDurationSeconds = $this->hitungDurasiDetik($invoice);
        $expiredAt = Carbon::now()->addSeconds($invoiceDurationSeconds);

        $externalId = sprintf('%s-%s', $invoice->no_invoice, now()->timestamp);
        $pelanggan = $invoice->pelanggan;
        $mobileNumber = self::formatNomorHpE164($pelanggan->no_hp);
        $payerEmail = (! empty($pelanggan->email) && filter_var($pelanggan->email, FILTER_VALIDATE_EMAIL))
            ? $pelanggan->email
            : null;

        // Line Items
        $items = [];
        $namaPaket = $invoice->layananPelanggan?->paketLayanan?->nama_paket ?? 'Langganan Internet';
        $items[] = new InvoiceItem([
            'name' => "Paket Internet: {$namaPaket}",
            'price' => (float) $invoice->jumlah,
            'quantity' => 1,
            'category' => 'Internet',
        ]);

        if ($invoice->promo_id && $invoice->promo) {
            $diskon = (float) ($invoice->jumlah - $invoice->jumlah_setelah_promo);
            if ($diskon > 0) {
                $items[] = new InvoiceItem([
                    'name' => "Diskon Promo: {$invoice->promo->nama_promo} ({$invoice->promo->kode_promo})",
                    'price' => -1 * $diskon,
                    'quantity' => 1,
                    'category' => 'Discount',
                ]);
            }
        }

        // Fees
        $fees = [];
        if ($fee > 0) {
            $fees[] = new InvoiceFee([
                'type' => 'Biaya Layanan Gateway',
                'value' => (float) $fee,
            ]);
        }

        $customerData = [
            'given_names' => ! empty($pelanggan->nama_depan) ? trim($pelanggan->nama_depan) : 'Pelanggan',
        ];

        if (! empty($pelanggan->nama_belakang)) {
            $customerData['surname'] = trim($pelanggan->nama_belakang);
        }

        if (! empty($payerEmail)) {
            $customerData['email'] = $payerEmail;
        }

        if (! empty($mobileNumber)) {
            $customerData['mobile_number'] = $mobileNumber;
            $customerData['phone_number'] = $mobileNumber;
        }

        // Catatan: customer_id tidak disetel dengan ID lokal (seperti no_reg)
        // karena Xendit menganggapnya referensi entitas Xendit Customers API yang dapat menggagalkan validasi e-wallet.
        $customerObj = new CustomerObject($customerData);

        $redirectUrl = route('portal.invoice.show', $invoice->id);

        $payloadRequest = [
            'external_id' => $externalId,
            'amount' => $totalTagihan,
            'payer_email' => $payerEmail,
            'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
            'invoice_duration' => $invoiceDurationSeconds,
            'customer' => $customerData,
            'success_redirect_url' => $redirectUrl,
            'failure_redirect_url' => $redirectUrl,
            'currency' => 'IDR',
        ];

        try {
            if (! empty($this->apiKey) && ! app()->environment('testing')) {
                $params = new CreateInvoiceRequest([
                    'external_id' => $externalId,
                    'amount' => $totalTagihan,
                    'payer_email' => $payerEmail,
                    'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
                    'invoice_duration' => (float) $invoiceDurationSeconds,
                    'customer' => $customerObj,
                    'items' => $items,
                    'fees' => $fees,
                    'success_redirect_url' => $redirectUrl,
                    'failure_redirect_url' => $redirectUrl,
                    'currency' => 'IDR',
                ]);

                $response = $this->invoiceApi->createInvoice(
                    create_invoice_request: $params
                );

                $responseArray = json_decode((string) json_encode($response), true) ?: [];
                $xenditId = $response->getId();
                $invoiceUrl = $response->getInvoiceUrl();
            } else {
                // Mock fallback untuk testing lokal
                $xenditId = 'inv_mock_'.uniqid();
                $invoiceUrl = 'https://checkout-staging.xendit.co/v2/'.$xenditId;
                $responseArray = [
                    'mock' => true,
                    'id' => $xenditId,
                    'invoice_url' => $invoiceUrl,
                    'status' => 'PENDING',
                    'external_id' => $externalId,
                    'amount' => $totalTagihan,
                ];
            }

            // Update record invoice lokal
            $invoice->update([
                'xendit_invoice_id' => $xenditId,
                'xendit_invoice_url' => $invoiceUrl,
                'xendit_status' => 'PENDING',
                'xendit_expired_at' => $expiredAt,
            ]);

            return TransaksiPaymentGateway::create([
                'invoice_id' => $invoice->id,
                'gateway' => 'xendit',
                'external_id' => $externalId,
                'xendit_reference_id' => $xenditId,
                'channel' => GatewayChannel::Invoice,
                'channel_detail' => 'hosted_invoice',
                'nomor_pembayaran' => $invoiceUrl,
                'total_tagihan' => $totalTagihan,
                'fee_gateway' => $fee,
                'status' => StatusTransaksiGateway::Pending,
                'expired_at' => $expiredAt,
                'payload_request' => $payloadRequest,
                'payload_response' => $responseArray,
            ]);
        } catch (XenditSdkException $e) {
            Log::error('Gagal membuat Hosted Invoice Xendit: '.$e->getMessage(), [
                'invoice' => $invoice->no_invoice,
                'error' => $e->getFullError(),
            ]);
            throw new Exception('Gagal membuat Invoice Xendit: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('Error pembuatan Hosted Invoice Xendit: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Sinkronisasikan status invoice langsung dengan Xendit API (self-healing / fallback saat redirect atau rekonsiliasi).
     *
     * @return array<string, mixed>
     */
    public function sinkronkanStatus(Invoice $invoice): array
    {
        $statusData = $this->cekStatusInvoice($invoice);
        $statusStr = strtoupper((string) ($statusData['status'] ?? ''));

        if (in_array($statusStr, ['PAID', 'SETTLED', 'SUCCEEDED'], true)) {
            $this->prosesPelunasanDariXendit($invoice, $statusData);
            $invoice->refresh();
        } elseif ($statusStr === 'EXPIRED') {
            if (! $invoice->isLunas()) {
                $invoice->update([
                    'xendit_invoice_url' => null,
                    'xendit_status' => 'EXPIRED',
                ]);
                $transaksi = $invoice->transaksiPaymentGatewayAktif();
                if ($transaksi && $transaksi->status === StatusTransaksiGateway::Pending) {
                    $transaksi->update(['status' => StatusTransaksiGateway::Expired]);
                }
            }
        }

        return $statusData;
    }

    /**
     * Eksekusi transaksi pelunasan invoice di database dengan row locking dan dispatch event post-commit.
     *
     * @param  array<string, mixed>|XenditCallbackData  $payloadOrData
     */
    public function prosesPelunasanDariXendit(
        Invoice $invoice,
        array|XenditCallbackData $payloadOrData,
        ?TransaksiPaymentGateway $transaksi = null,
        ?WebhookLog $webhookLog = null
    ): bool {
        $eventToDispatch = null;

        if ($payloadOrData instanceof XenditCallbackData) {
            $callbackData = $payloadOrData;
            $rawPayload = $callbackData->rawPayload;
            $amount = $callbackData->amount;
            $paidAtStr = $callbackData->paidAt;
            $channel = $callbackData->channel;
            $channelDetail = $callbackData->channelDetail;
            $paymentRef = $callbackData->paymentReference;
        } else {
            $rawPayload = $payloadOrData;
            $callbackData = null;
            $amount = (float) ($rawPayload['paid_amount'] ?? ($rawPayload['amount'] ?? $invoice->jumlah_setelah_promo));
            $paidAtStr = (string) ($rawPayload['paid_at'] ?? ($rawPayload['updated'] ?? now()->toIso8601String()));
            $pm = strtoupper((string) ($rawPayload['payment_method'] ?? ''));
            $channel = match ($pm) {
                'BANK_TRANSFER', 'VIRTUAL_ACCOUNT' => 'virtual_account',
                'QR_CODE', 'QRIS' => 'qris',
                'EWALLET', 'E_WALLET' => 'ewallet',
                default => 'invoice',
            };
            $channelDetail = isset($rawPayload['payment_channel']) ? strtolower((string) $rawPayload['payment_channel']) : null;
            $paymentRef = (string) ($rawPayload['payment_destination'] ?? ($rawPayload['payment_id'] ?? ($rawPayload['id'] ?? null)));
        }

        if (! $transaksi) {
            $transaksi = $invoice->transaksiPaymentGatewayAktif();
        }

        DB::transaction(function () use ($invoice, $transaksi, $webhookLog, $rawPayload, $amount, $paidAtStr, $channel, $channelDetail, $paymentRef, &$eventToDispatch) {
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
                $lockedInvoice->update(['xendit_status' => 'PAID']);
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
                    'payload_response' => $rawPayload,
                ]);
            }

            // 2. Buat Record Pembayaran
            $channelDetailText = $channelDetail ? strtoupper($channelDetail) : '';
            $nominalBayar = $amount > 0 ? $amount : (float) ($transaksi?->total_tagihan ?? $lockedInvoice->jumlah_setelah_promo);

            $pembayaran = Pembayaran::create([
                'invoice_id' => $lockedInvoice->id,
                'metode' => MetodePembayaran::PaymentGateway,
                'referensi_transaksi' => $paymentRef ?: ($transaksi?->external_id ?? $lockedInvoice->no_invoice),
                'jumlah_dibayar' => $nominalBayar,
                'dibayar_pada' => $dibayarPada,
                'catatan' => trim("Pembayaran otomatis Xendit {$channel} {$channelDetailText}"),
            ]);

            // 3. Update Status Invoice
            $lockedInvoice->update([
                'status' => StatusInvoice::Lunas,
                'tanggal_lunas' => $dibayarPada->toDateString(),
                'metode_pembayaran' => MetodePembayaran::PaymentGateway,
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

            // Siapkan event untuk dipancarkan setelah commit DB selesai
            $eventToDispatch = new InvoicePaidEvent($lockedInvoice, $pembayaran);
        });

        if ($eventToDispatch) {
            event($eventToDispatch);
        }

        return true;
    }

    /**
     * Alias legacy untuk kompatibilitas.
     */
    public function buatVirtualAccount(Invoice $invoice, string $bankCode = 'BCA'): TransaksiPaymentGateway
    {
        return $this->buatInvoice($invoice);
    }

    /**
     * Alias legacy untuk kompatibilitas.
     */
    public function buatQris(Invoice $invoice): TransaksiPaymentGateway
    {
        return $this->buatInvoice($invoice);
    }

    /**
     * Cek status invoice ke Xendit API (untuk rekonsiliasi admin).
     *
     * @return array<string, mixed>
     */
    public function cekStatusInvoice(Invoice|TransaksiPaymentGateway $target): array
    {
        $xenditId = $target instanceof Invoice
            ? $target->xendit_invoice_id
            : ($target->xendit_reference_id ?: $target->invoice->xendit_invoice_id);

        if (empty($this->apiKey) || app()->environment('testing')) {
            return [
                'status' => 'PENDING',
                'message' => 'Mode Test/Offline: Status invoice aktif.',
            ];
        }

        if (empty($xenditId)) {
            return [
                'error' => 'Invoice belum memiliki referensi ID Xendit.',
            ];
        }

        try {
            $response = $this->invoiceApi->getInvoiceById($xenditId);

            return json_decode((string) json_encode($response), true) ?: [];
        } catch (Exception $e) {
            Log::error("Gagal cek status invoice Xendit {$xenditId}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cek status transaksi pembayaran ke Xendit API (kompatibilitas).
     *
     * @return array<string, mixed>
     */
    public function cekStatusTransaksi(TransaksiPaymentGateway $transaksi): array
    {
        return $this->cekStatusInvoice($transaksi);
    }

    /**
     * Cek koneksi dan validitas API Key ke Xendit API.
     *
     * @return array<string, mixed>
     */
    public function cekKoneksiApi(): array
    {
        if (app()->environment('testing')) {
            return [
                'success' => true,
                'message' => 'Koneksi ke Xendit API berhasil terhubung (Testing Mock).',
                'balance' => 15000000.0,
                'currency' => 'IDR',
            ];
        }

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'XENDIT_SECRET_KEY belum dikonfigurasi di file .env',
            ];
        }

        try {
            $balanceApi = new BalanceApi(
                client: new Client(['timeout' => 15]),
                config: Configuration::getDefaultConfiguration()
            );

            $balance = $balanceApi->getBalance('CASH');
            $balanceData = json_decode((string) json_encode($balance), true) ?: [];

            return [
                'success' => true,
                'message' => 'Koneksi ke Xendit API berhasil terhubung.',
                'balance' => $balanceData['balance'] ?? ($balance->getBalance() ?? null),
                'currency' => 'IDR',
            ];
        } catch (XenditSdkException $e) {
            Log::error('Gagal ping koneksi Xendit API: '.$e->getMessage(), [
                'error' => $e->getFullError(),
            ]);

            return [
                'success' => false,
                'message' => 'Autentikasi Xendit gagal atau API Key tidak valid: '.$e->getMessage(),
                'error' => $e->getFullError(),
            ];
        } catch (Exception $e) {
            Log::error('Error ping koneksi Xendit API: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal menghubungi Xendit API: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Simulasikan pembayaran di sandbox Xendit.
     *
     * @return array<string, mixed>
     */
    public function simulasikanPembayaran(TransaksiPaymentGateway $transaksi): array
    {
        // Untuk hosted invoice sandbox, simulasi lokal adalah metode paling andal
        return $this->simulasikanWebhookLokal($transaksi);
    }

    /**
     * Simulasikan penerimaan Webhook lokal secara internal tanpa butuh tunnel publik.
     *
     * @return array<string, mixed>
     */
    public function simulasikanWebhookLokal(TransaksiPaymentGateway|Invoice $target): array
    {
        $token = (string) config('services.xendit.callback_token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'XENDIT_CALLBACK_TOKEN belum dikonfigurasi di file .env',
            ];
        }

        if ($target instanceof Invoice) {
            $invoice = $target;
            $transaksi = $invoice->transaksiPaymentGatewayAktif();
        } else {
            $transaksi = $target;
            $invoice = $transaksi->invoice;
        }

        $xenditId = $invoice->xendit_invoice_id ?: ($transaksi?->xendit_reference_id ?: 'sim_inv_'.uniqid());
        $externalId = $transaksi?->external_id ?: "{$invoice->no_invoice}-".now()->timestamp;
        $amount = $transaksi ? (float) $transaksi->total_tagihan : (float) $invoice->jumlah_setelah_promo;

        $payload = [
            'id' => $xenditId,
            'external_id' => $externalId,
            'user_id' => 'sim_user_'.uniqid(),
            'status' => 'PAID',
            'merchant_name' => 'UNMS ISP',
            'amount' => $amount,
            'paid_amount' => $amount,
            'payer_email' => $invoice->pelanggan->email,
            'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
            'payment_method' => 'BANK_TRANSFER',
            'payment_channel' => 'BCA',
            'payment_destination' => '880812345678',
            'paid_at' => Carbon::now()->toIso8601String(),
            'created' => Carbon::now()->subMinutes(5)->toIso8601String(),
            'updated' => Carbon::now()->toIso8601String(),
        ];

        $rawJson = json_encode($payload);
        $request = Request::create(
            uri: '/webhook/xendit',
            method: 'POST',
            parameters: $payload,
            cookies: [],
            files: [],
            server: [
                'HTTP_X_CALLBACK_TOKEN' => $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $rawJson
        );
        $request->headers->set('x-callback-token', $token);

        /** @var XenditWebhookController $controller */
        $controller = app(XenditWebhookController::class);
        $response = $controller->handle($request);
        $responseData = json_decode((string) $response->getContent(), true) ?: [];

        $isSuccess = $response->getStatusCode() === 200;

        return [
            'success' => $isSuccess,
            'status_code' => $response->getStatusCode(),
            'message' => $isSuccess ? 'Simulasi webhook lokal berhasil diproses.' : 'Webhook lokal gagal diproses.',
            'response' => $responseData,
        ];
    }

    /**
     * Hitung durasi detik kedaluwarsa link Xendit (berbasis jatuh tempo, minimal 24 jam / 86400 detik).
     */
    public function hitungDurasiDetik(Invoice $invoice): int
    {
        $minDuration = 86400; // 24 jam
        $due = Carbon::parse($invoice->tanggal_jatuh_tempo)->endOfDay();
        $now = Carbon::now();

        if ($due->isPast() || $due->diffInSeconds($now) < $minDuration) {
            return $minDuration;
        }

        return $due->diffInSeconds($now);
    }

    /**
     * Format nomor HP ke standar E.164 Indonesia (+628xxxxxxxxxx) yang diterima oleh Xendit E-Wallet & Notification.
     */
    public static function formatNomorHpE164(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Hapus semua karakter selain angka
        $clean = preg_replace('/[^\d]/', '', $phone);
        if (empty($clean)) {
            return null;
        }

        // Ubah format 08xxx atau 8xxx menjadi 628xxx
        if (str_starts_with($clean, '08')) {
            $clean = '628'.substr($clean, 2);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '628'.substr($clean, 1);
        }

        // Validasi panjang standar nomor seluler Indonesia (10-15 digit)
        if (str_starts_with($clean, '62') && strlen($clean) >= 10 && strlen($clean) <= 15) {
            return '+'.$clean;
        }

        return '+'.$clean;
    }
}
