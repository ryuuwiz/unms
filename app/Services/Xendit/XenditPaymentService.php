<?php

namespace App\Services\Xendit;

use App\Enums\GatewayChannel;
use App\Enums\StatusTransaksiGateway;
use App\Http\Controllers\Webhook\XenditWebhookController;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $externalId = sprintf('INV-%s-%s', $invoice->no_invoice, now()->timestamp);
        $pelanggan = $invoice->pelanggan;
        $mobileNumber = $pelanggan->no_hp ? '+'.$pelanggan->no_hp : null;

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

        $customerObj = new CustomerObject([
            'given_names' => $pelanggan->nama_depan,
            'surname' => $pelanggan->nama_belakang ?? '',
            'email' => $pelanggan->email,
            'mobile_number' => $mobileNumber,
            'customer_id' => (string) $pelanggan->no_reg,
        ]);

        $redirectUrl = route('portal.invoice.show', $invoice->id);

        $payloadRequest = [
            'external_id' => $externalId,
            'amount' => $totalTagihan,
            'payer_email' => $pelanggan->email,
            'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
            'invoice_duration' => $invoiceDurationSeconds,
            'customer' => [
                'given_names' => $pelanggan->nama_depan,
                'surname' => $pelanggan->nama_belakang ?? '',
                'email' => $pelanggan->email,
                'mobile_number' => $mobileNumber,
                'customer_id' => (string) $pelanggan->no_reg,
            ],
            'success_redirect_url' => $redirectUrl,
            'failure_redirect_url' => $redirectUrl,
            'currency' => 'IDR',
        ];

        try {
            if (! empty($this->apiKey) && ! app()->environment('testing')) {
                $params = new CreateInvoiceRequest([
                    'external_id' => $externalId,
                    'amount' => $totalTagihan,
                    'payer_email' => $pelanggan->email,
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
        $externalId = $transaksi?->external_id ?: "INV-{$invoice->no_invoice}-".now()->timestamp;
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
}
