<?php

namespace App\Services\PaymentGateway\Drivers;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\GatewayChannel;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\DeskripsiTagihanBuilder;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Xendit\BalanceAndTransaction\BalanceApi;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\XenditSdkException;

class XenditDriver extends AbstractPaymentDriver
{
    public const PROVIDER = 'xendit';

    private const BASE_URL = 'https://api.xendit.co';

    public function getProviderName(): string
    {
        return self::PROVIDER;
    }

    public function getProviderLabel(): string
    {
        return 'Xendit Payment Session';
    }

    /**
     * Dapatkan API Key Xendit dari pengaturan gateway terenkripsi.
     */
    protected function getApiKey(PengaturanGateway $setting): string
    {
        return (string) $setting->getCredential('secret_key', '');
    }

    /**
     * Dapatkan Callback Token Xendit dari pengaturan gateway terenkripsi.
     */
    protected function getCallbackToken(PengaturanGateway $setting): string
    {
        return (string) $setting->getCredential('callback_token', '');
    }

    /**
     * Inisialisasi InvoiceApi SDK.
     */
    protected function getInvoiceApi(string $apiKey): InvoiceApi
    {
        Configuration::setXenditKey($apiKey);

        return new InvoiceApi(
            client: new Client(['timeout' => 30]),
            config: Configuration::getDefaultConfiguration()
        );
    }

    /**
     * Event Payment Session/Payments API yang berarti uang sudah masuk. Dokumen Xendit menyebut
     * `payment.capture` (referensi webhook) sekaligus `payment.succeeded` (panduan migrasi).
     */
    private const EVENT_LUNAS = ['payment.capture', 'payment.succeeded', 'payment_session.completed'];

    /** Event Payment Session yang mengakhiri link tanpa pembayaran. */
    private const EVENT_BERAKHIR = ['payment_session.expired'];

    /**
     * Masa berlaku Payment Session. Tetap 24 jam: aturan durasi invoice (hitungDurasiDetik) tidak pernah
     * di bawah 24 jam, sedangkan batas maksimum expires_at tidak didokumentasikan Xendit. Session yang
     * lewat diganti saat pelanggan menekan tombol lagi.
     */
    private const MASA_BERLAKU_SESSION_JAM = 24;

    /**
     * Kode kanal Payment Session untuk metode bayar; `allowed_payment_channels` menerima kode kanal,
     * bukan kategori, jadi "hanya VA" berarti mendaftarkan semua bank VA (ADR-0073).
     *
     * @return list<string>
     */
    public static function kanalUntuk(?GatewayChannel $metode): array
    {
        return match ($metode) {
            GatewayChannel::VirtualAccount => [
                'BCA_VIRTUAL_ACCOUNT', 'BNI_VIRTUAL_ACCOUNT', 'BRI_VIRTUAL_ACCOUNT', 'MANDIRI_VIRTUAL_ACCOUNT',
                'PERMATA_VIRTUAL_ACCOUNT', 'BSI_VIRTUAL_ACCOUNT', 'BNC_VIRTUAL_ACCOUNT', 'BSS_VIRTUAL_ACCOUNT',
                'MUAMALAT_VIRTUAL_ACCOUNT',
            ],
            GatewayChannel::Qris => ['QRIS'],
            default => throw new Exception('Metode bayar Xendit harus Virtual Account atau QRIS.'),
        };
    }

    private function http(string $apiKey): PendingRequest
    {
        return Http::withBasicAuth($apiKey, '')->timeout(30);
    }

    public function menghitungBiayaSendiri(): bool
    {
        return false;
    }

    /**
     * Buat Payment Session hosted (`POST /sessions`) yang hanya memuat kanal metode bayar terpilih,
     * dengan nominal tagihan + Biaya Admin Gateway metode itu.
     */
    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting, ?string $externalId = null, ?GatewayChannel $metode = null, int $biayaAdmin = 0): PaymentLinkResponse
    {
        $kanal = self::kanalUntuk($metode);

        $apiKey = $this->getApiKey($setting);
        if (empty($apiKey)) {
            throw new Exception('Xendit Secret Key belum dikonfigurasi. Payment link tidak dapat diterbitkan.');
        }

        $nominalInvoice = (int) round((float) $invoice->jumlah_setelah_promo);
        $totalTagihan = $nominalInvoice + $biayaAdmin;
        $externalId = $externalId ?: $this->generateExternalId($invoice);
        $expiredAt = Carbon::now()->addHours(self::MASA_BERLAKU_SESSION_JAM);
        // Tautan Tagihan, bukan /tagihan/{id}: pelanggan yang membayar tanpa login harus bisa
        // kembali ke halaman tagihannya setelah membayar (ADR-0067).
        $redirectUrl = $invoice->tautanTagihan();

        $body = [
            'reference_id' => $externalId,
            'session_type' => 'PAY',
            'mode' => 'PAYMENT_LINK',
            'country' => 'ID',
            'currency' => 'IDR',
            'amount' => $totalTagihan,
            'allowed_payment_channels' => $kanal,
            'expires_at' => $expiredAt->toIso8601ZuluString(),
            'description' => app(DeskripsiTagihanBuilder::class)->buat($invoice),
            'customer' => $this->dataCustomer($invoice),
            'items' => $this->itemTagihan($invoice, $nominalInvoice, $biayaAdmin),
            'success_return_url' => $redirectUrl,
            'cancel_return_url' => $redirectUrl,
        ];

        $response = $this->http($apiKey)->post(self::BASE_URL.'/sessions', $body);

        if ($response->failed()) {
            Log::error('Gagal membuat Payment Session Xendit', [
                'invoice' => $invoice->no_invoice,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);

            throw new Exception('Gagal membuat Payment Session Xendit: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return new PaymentLinkResponse(
            paymentId: (string) $response->json('payment_session_id'),
            paymentUrl: (string) $response->json('payment_link_url'),
            externalId: $externalId,
            amount: (float) $totalTagihan,
            expiredAt: $expiredAt,
            channel: $metode,
            channelDetail: 'payment_session',
            rawResponse: $response->json() ?? [],
        );
    }

    /**
     * Struk gerai mencetak `given_names` sebagai Nama Konsumen, jadi diisi No. Registrasi agar kasir
     * dan admin dapat mencocokkan pembayaran; nama asli tetap tercatat di `surname`.
     *
     * @return array<string, mixed>
     */
    private function dataCustomer(Invoice $invoice): array
    {
        $pelanggan = $invoice->pelanggan;
        $namaLengkap = trim(($pelanggan->nama_depan ?? '').' '.($pelanggan->nama_belakang ?? ''));

        return array_filter([
            'type' => 'INDIVIDUAL',
            'reference_id' => $pelanggan->no_reg ?: 'pelanggan-'.$invoice->pelanggan_id,
            'email' => filter_var($pelanggan->email, FILTER_VALIDATE_EMAIL) ? $pelanggan->email : null,
            'mobile_number' => self::formatNomorHpE164($pelanggan->no_hp),
            'individual_detail' => array_filter([
                'given_names' => $pelanggan->no_reg ?: ($namaLengkap ?: 'Pelanggan'),
                'surname' => $pelanggan->no_reg && $namaLengkap !== '' ? $namaLengkap : null,
            ]),
        ]);
    }

    /**
     * Rincian di halaman checkout; jumlah item = `amount` (tagihan setelah promo + biaya admin).
     *
     * @return list<array<string, mixed>>
     */
    private function itemTagihan(Invoice $invoice, int $nominalInvoice, int $biayaAdmin): array
    {
        $namaPaket = $invoice->layananPelanggan?->paketLayanan->nama_paket ?? 'Langganan Internet';
        $items = [[
            'reference_id' => $invoice->no_invoice,
            'type' => 'DIGITAL_SERVICE',
            'name' => "Paket Internet: {$namaPaket}",
            'net_unit_amount' => $nominalInvoice,
            'quantity' => 1,
            'category' => 'Internet',
        ]];

        if ($biayaAdmin > 0) {
            $items[] = [
                'reference_id' => 'biaya-admin',
                'type' => 'FEE',
                'name' => 'Biaya Layanan Gateway',
                'net_unit_amount' => $biayaAdmin,
                'quantity' => 1,
                'category' => 'Fee',
            ];
        }

        return $items;
    }

    public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
    {
        // Transaksi hanya dicek dengan referensinya sendiri: meminjam ID invoice terkini akan
        // mengecek transaksi lama terhadap invoice Xendit milik transaksi lain (ADR-0067).
        // Tanpa referensi, transaksi dicari lewat external_id-nya.
        $xenditId = $target instanceof Invoice
            ? ($target->payment_gateway_id ?: $target->xendit_invoice_id)
            : ($target->xendit_reference_id ?: $target->provider_reference_id);

        $apiKey = $this->getApiKey($setting);

        if (str_starts_with((string) $xenditId, 'ps-')) {
            return $this->statusPaymentSession((string) $xenditId, $apiKey);
        }

        // Hanya 'testing' yang dipercaya untuk mock link lama /v2/invoices, lihat ADR 0039.
        // APP_ENV yang salah konfigurasi di produksi tidak lagi bisa membuat status pembayaran
        // selalu dilaporkan PENDING palsu yang menutupi status asli invoice selamanya.
        if (app()->environment('testing')) {
            return [
                'status' => 'PENDING',
                'message' => 'Mode Test/Offline: Status invoice aktif.',
            ];
        }

        if (empty($apiKey)) {
            return [
                'error' => 'Xendit Secret Key belum dikonfigurasi.',
            ];
        }

        if (empty($xenditId) && $target instanceof TransaksiPaymentGateway && ! empty($target->external_id)) {
            return $this->findInvoiceByExternalId($target->external_id, $apiKey);
        }

        if (empty($xenditId)) {
            return [
                'error' => 'Invoice belum memiliki referensi ID Xendit.',
            ];
        }

        // ID V3 Payment Request (pr-xxx / py-xxx) dicek lewat endpoint V3.
        if (str_starts_with($xenditId, 'pr-') || str_starts_with($xenditId, 'py-') || str_starts_with($xenditId, 'pr_')) {
            return $this->checkPaymentRequestV3Status($xenditId, $apiKey);
        }

        return $this->statusInvoiceLama($xenditId, $target, $apiKey);
    }

    /**
     * Status link lama `/v2/invoices`, dengan pemulihan lewat external_id dan fallback ke V3 Payment Requests.
     *
     * @return array<string, mixed>
     */
    private function statusInvoiceLama(string $xenditId, Invoice|TransaksiPaymentGateway $target, string $apiKey): array
    {
        try {
            $invoiceApi = $this->getInvoiceApi($apiKey);
            $response = $invoiceApi->getInvoiceById($xenditId);

            return json_decode((string) json_encode($response), true) ?: [];
        } catch (Exception $e) {
            $externalId = $target instanceof TransaksiPaymentGateway
                ? $target->external_id
                : $target->transaksiPaymentGatewayAktif()?->external_id;

            if (! empty($externalId)) {
                $recovered = $this->findInvoiceByExternalId($externalId, $apiKey);

                if (! isset($recovered['error'])) {
                    return $recovered;
                }

                if (isset($recovered['error_code'])) {
                    return $recovered;
                }
            }

            // Fallback coba periksa ke V3 Payment Requests API jika invoice ID tidak ditemukan
            try {
                $v3Result = $this->checkPaymentRequestV3Status($xenditId, $apiKey);
                if (! empty($v3Result) && ! isset($v3Result['error'])) {
                    return $v3Result;
                }
            } catch (Exception $fallbackError) {
                Log::warning("Fallback V3 cek status Xendit {$xenditId} gagal: ".$fallbackError->getMessage());
            }

            Log::error("Gagal cek status invoice Xendit {$xenditId}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Status Payment Session (`GET /sessions/{id}`) dalam bentuk yang sama dengan status invoice lama.
     *
     * @return array<string, mixed>
     */
    private function statusPaymentSession(string $sessionId, string $apiKey): array
    {
        $response = $this->http($apiKey)->get(self::BASE_URL."/sessions/{$sessionId}");

        if ($response->failed()) {
            return ['error' => "HTTP {$response->status()}: {$response->body()}"];
        }

        $data = $response->json();
        $data['raw_status'] = strtoupper((string) ($data['status'] ?? ''));
        $data['status'] = match ($data['raw_status']) {
            'COMPLETED' => 'PAID',
            'EXPIRED', 'CANCELED' => 'EXPIRED',
            default => 'PENDING',
        };
        $data['paid_amount'] = $data['status'] === 'PAID' ? (float) ($data['amount'] ?? 0) : 0.0;

        return $data;
    }

    /**
     * Pulihkan invoice melalui external_id yang dibuat oleh aplikasi.
     *
     * @return array<string, mixed>
     */
    protected function findInvoiceByExternalId(string $externalId, string $apiKey): array
    {
        try {
            $invoices = $this->getInvoiceApi($apiKey)->getInvoices(
                null,
                $externalId,
                null,
                10
            );
        } catch (Exception $e) {
            Log::warning("Fallback external_id Xendit {$externalId} gagal: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }

        $matches = array_values(array_filter(
            $invoices,
            fn ($invoice): bool => $invoice->getExternalId() === $externalId
        ));

        if (count($matches) !== 1) {
            return [
                'error' => count($matches) === 0
                    ? 'Invoice Xendit tidak ditemukan berdasarkan external_id.'
                    : 'Lebih dari satu invoice Xendit ditemukan berdasarkan external_id.',
                'error_code' => count($matches) === 0 ? 'external_id_not_found' : 'ambiguous_external_id',
                'external_id' => $externalId,
            ];
        }

        $invoice = $matches[0];
        $status = strtoupper((string) $invoice->getStatus());
        $amount = (float) $invoice->getAmount();

        return [
            'id' => (string) $invoice->getId(),
            'external_id' => $externalId,
            'invoice_url' => (string) $invoice->getInvoiceUrl(),
            'status' => $status,
            'amount' => $amount,
            'paid_amount' => $status === 'PAID' || $status === 'SETTLED' ? $amount : 0.0,
            'recovered_by' => 'external_id',
        ];
    }

    /**
     * Cek status transaksi via Xendit V3 Payment Requests API (/v3/payment_requests/{id}).
     *
     * @return array<string, mixed>
     */
    public function checkPaymentRequestV3Status(string $paymentRequestId, string $apiKey): array
    {
        try {
            $response = Http::withBasicAuth($apiKey, '')
                ->withHeaders([
                    'api-version' => '2024-05-01',
                ])
                ->timeout(20)
                ->get("https://api.xendit.co/v3/payment_requests/{$paymentRequestId}");

            if ($response->successful()) {
                $data = $response->json();
                $rawStatus = strtoupper((string) ($data['status'] ?? ''));
                $status = match ($rawStatus) {
                    'SUCCEEDED', 'PAID', 'CAPTURED', 'SETTLED' => 'PAID',
                    'FAILED', 'FAILURE', 'DECLINED' => 'FAILED',
                    'EXPIRED', 'CANCELLED' => 'EXPIRED',
                    default => 'PENDING',
                };
                $data['status'] = $status;
                $data['raw_status'] = $rawStatus;
                $data['paid_amount'] = (float) ($data['capture_amount'] ?? ($data['amount'] ?? 0));

                return $data;
            }

            return ['error' => "HTTP {$response->status()}: {$response->body()}"];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function verifyWebhook(Request $request, PengaturanGateway $setting): bool
    {
        $headerToken = $request->header('x-callback-token')
            ?: ($request->header('webhook-token') ?: $request->header('x-webhook-token'));
        $expectedToken = $this->getCallbackToken($setting);

        if (empty($expectedToken)) {
            Log::warning('Xendit Webhook Token belum dikonfigurasi di database/env.');

            return false;
        }

        if (empty($headerToken)) {
            return false;
        }

        return hash_equals($expectedToken, $headerToken);
    }

    public function parseWebhookPayload(Request $request): PaymentCallbackData
    {
        return $this->petakanPayload($request->all());
    }

    /**
     * Petakan payload invoice/pembayaran Xendit (callback). Mata uang
     * ikut dipetakan untuk penegakan strict-IDR (ADR 0028 §2).
     *
     * @param  array<string, mixed>  $payload
     */
    public function petakanPayload(array $payload): PaymentCallbackData
    {
        $externalId = $this->externalIdDari($payload);
        [$channel, $channelDetail] = $this->channelDari($payload);

        return new PaymentCallbackData(
            provider: 'xendit',
            externalId: $externalId,
            status: $this->statusDari($payload),
            paidAmount: $this->nominalDari($payload),
            eventId: $this->eventIdDari($payload),
            paidAt: $this->waktuBayarDari($payload),
            channel: $channel,
            channelDetail: $channelDetail,
            paymentReference: $this->referensiPembayaranDari($payload),
            isTest: $this->payloadUjiCoba($payload, $externalId),
            rawPayload: $payload,
            currency: $this->mataUangDari($payload),
            linkId: is_string($payload['data']['payment_session_id'] ?? null) ? $payload['data']['payment_session_id'] : null,
        );
    }

    /**
     * Payment method bisa berupa string (v1 / invoice) maupun objek (v2 / v3 payment_requests).
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: GatewayChannel, 1: string|null}
     */
    private function channelDari(array $payload): array
    {
        $rawPm = $payload['payment_method'] ?? ($payload['data']['payment_method'] ?? ($payload['data']['type'] ?? ''));
        $channelDetail = null;

        if (is_array($rawPm)) {
            $pmType = $rawPm['type'] ?? ($rawPm['payment_method_type'] ?? '');
            $pm = is_string($pmType) ? strtoupper(trim($pmType)) : '';

            $extractedChannel = $rawPm['ewallet']['channel_code']
                ?? ($rawPm['virtual_account']['channel_code']
                ?? ($rawPm['qr_code']['channel_code']
                ?? ($rawPm['direct_debit']['channel_code']
                ?? ($rawPm['over_the_counter']['channel_code']
                ?? ($rawPm['card']['channel_properties']['card_brand'] ?? null)))));

            if (is_string($extractedChannel)) {
                $channelDetail = strtolower(trim($extractedChannel));
            }
        } else {
            $pm = is_string($rawPm) ? strtoupper(trim($rawPm)) : '';
        }

        $channel = match ($pm) {
            'BANK_TRANSFER', 'VIRTUAL_ACCOUNT' => GatewayChannel::VirtualAccount,
            'QR_CODE', 'QRIS', 'QR' => GatewayChannel::Qris,
            'EWALLET', 'E_WALLET' => GatewayChannel::Ewallet,
            'RETAIL_OUTLET', 'OVER_THE_COUNTER' => GatewayChannel::RetailOutlet,
            default => GatewayChannel::Invoice,
        };

        return [$channel, $channelDetail ?: $this->kodeChannelDari($payload)];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function kodeChannelDari(array $payload): ?string
    {
        $rawChannel = $payload['payment_channel']
            ?? ($payload['data']['payment_channel']
            ?? ($payload['data']['channel_code']
            ?? ($payload['channel_code'] ?? null)));

        return match (true) {
            is_string($rawChannel) => strtolower(trim($rawChannel)),
            is_array($rawChannel) => strtolower((string) ($rawChannel['channel_code'] ?? ($rawChannel['code'] ?? ''))),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function externalIdDari(array $payload): string
    {
        $rawExternalId = $payload['external_id']
            ?? ($payload['data']['reference_id']
            ?? ($payload['data']['external_id']
            ?? ($payload['reference_id'] ?? '')));

        return is_string($rawExternalId) ? trim($rawExternalId) : (is_numeric($rawExternalId) ? (string) $rawExternalId : '');
    }

    /**
     * Status dari nama event V3 (mis. `payment.succeeded`) bila ada, selain itu dari atribut status.
     *
     * @param  array<string, mixed>  $payload
     */
    private function statusDari(array $payload): string
    {
        $eventName = strtolower(trim((string) ($payload['event'] ?? '')));
        if (in_array($eventName, self::EVENT_LUNAS, true)) {
            return 'PAID';
        }
        if (in_array($eventName, self::EVENT_BERAKHIR, true)) {
            return 'EXPIRED';
        }

        $rawStatus = $payload['status'] ?? ($payload['data']['status'] ?? '');
        $statusStr = is_string($rawStatus) ? strtoupper(trim($rawStatus)) : '';

        if (! empty($eventName)) {
            return match (true) {
                str_contains($eventName, 'succeeded'), str_contains($eventName, 'paid'), str_contains($eventName, 'capture'), str_contains($eventName, 'settled') => 'PAID',
                str_contains($eventName, 'failure'), str_contains($eventName, 'failed'), str_contains($eventName, 'declined') => 'FAILED',
                str_contains($eventName, 'expired'), str_contains($eventName, 'cancelled') => 'EXPIRED',
                default => in_array($statusStr, ['SUCCEEDED', 'PAID', 'SETTLED', 'CAPTURED', 'BERHASIL'], true) ? 'PAID' : (in_array($statusStr, ['FAILED', 'FAILURE', 'DECLINED'], true) ? 'FAILED' : (in_array($statusStr, ['EXPIRED', 'CANCELLED'], true) ? 'EXPIRED' : 'PENDING')),
            };
        }

        return match (true) {
            in_array($statusStr, ['SUCCEEDED', 'COMPLETED', 'PAID', 'SETTLED', 'CAPTURED', 'BERHASIL'], true) => 'PAID',
            in_array($statusStr, ['FAILED', 'FAILURE', 'DECLINED', 'GAGAL'], true) => 'FAILED',
            in_array($statusStr, ['EXPIRED', 'CANCELLED', 'KEDALUWARSA'], true) => 'EXPIRED',
            default => 'PENDING',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nominalDari(array $payload): float
    {
        $rawAmount = $payload['paid_amount']
            ?? $payload['amount']
            ?? $payload['data']['capture_amount']
            ?? $payload['data']['captures'][0]['capture_amount']
            ?? $payload['data']['request_amount']
            ?? $payload['data']['amount']
            ?? 0;

        return is_numeric($rawAmount) ? (float) $rawAmount : 0.0;
    }

    /**
     * Identifier event / payment request / payment.
     *
     * @param  array<string, mixed>  $payload
     */
    private function eventIdDari(array $payload): ?string
    {
        $rawEventId = $payload['id']
            ?? ($payload['data']['payment_id'] ?? null)
            ?? ($payload['data']['id'] ?? null)
            ?? ($payload['data']['payment_request_id'] ?? null)
            ?? ($payload['data']['payment_session_id'] ?? null)
            ?? ($payload['payment_id'] ?? null)
            ?? ($payload['event_id'] ?? null)
            ?? ($payload['callback_virtual_account_id'] ?? null)
            ?? ($payload['qr_id'] ?? null);

        return (is_string($rawEventId) || is_numeric($rawEventId)) && ! empty($rawEventId) ? trim((string) $rawEventId) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function waktuBayarDari(array $payload): string
    {
        $rawPaidAt = $payload['paid_at']
            ?? ($payload['updated']
            ?? ($payload['data']['updated']
            ?? ($payload['created']
            ?? ($payload['data']['created'] ?? null))));

        return is_string($rawPaidAt) ? $rawPaidAt : now()->toIso8601String();
    }

    /**
     * URL redirect action bila ada, selain itu VA / QR / ID pembayaran.
     *
     * @param  array<string, mixed>  $payload
     */
    private function referensiPembayaranDari(array $payload): ?string
    {
        foreach ((array) ($payload['data']['actions'] ?? []) as $action) {
            if (isset($action['url']) && is_string($action['url'])) {
                return $action['url'];
            }
        }

        $rawPaymentRef = $payload['payment_destination']
            ?? ($payload['data']['payment_id'] ?? null)
            ?? ($payload['payment_id']
            ?? ($payload['id']
            ?? ($payload['data']['id'] ?? null)));

        return (is_string($rawPaymentRef) || is_numeric($rawPaymentRef)) ? (string) $rawPaymentRef : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadUjiCoba(array $payload, string $externalId): bool
    {
        return str_contains($externalId, '123124123')
            || str_contains($externalId, 'test-')
            || (bool) ($payload['is_test'] ?? false)
            || (bool) ($payload['data']['is_test'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mataUangDari(array $payload): ?string
    {
        $rawCurrency = $payload['currency'] ?? ($payload['data']['currency'] ?? null);

        return is_string($rawCurrency) && $rawCurrency !== '' ? strtoupper(trim($rawCurrency)) : null;
    }

    public function pingConnection(PengaturanGateway $setting): PingConnectionResult
    {
        $apiKey = $this->getApiKey($setting);

        if (app()->environment('testing')) {
            return new PingConnectionResult(
                success: true,
                message: 'Koneksi ke Xendit API berhasil terhubung (Mock Test).',
                balance: 15000000.0,
                currency: 'IDR'
            );
        }

        if (empty($apiKey)) {
            Log::warning('Pengujian koneksi Xendit gagal: Secret Key belum dikonfigurasi.', [
                'gateway_id' => $setting->id,
                'provider' => $setting->provider,
            ]);

            return new PingConnectionResult(
                success: false,
                message: 'Secret Key Xendit belum dikonfigurasi.'
            );
        }

        try {
            Configuration::setXenditKey($apiKey);
            $balanceApi = new BalanceApi(
                client: new Client(['timeout' => 15]),
                config: Configuration::getDefaultConfiguration()
            );

            $balance = $balanceApi->getBalance('CASH');
            $balanceData = json_decode((string) json_encode($balance), true) ?: [];
            $amount = (float) ($balanceData['balance'] ?? $balance->getBalance());

            Log::info('Pengujian koneksi Xendit berhasil.', [
                'gateway_id' => $setting->id,
                'provider' => $setting->provider,
            ]);

            return new PingConnectionResult(
                success: true,
                message: 'Koneksi ke Xendit API berhasil terhubung.',
                balance: $amount,
                currency: 'IDR',
                rawResponse: $balanceData
            );
        } catch (XenditSdkException $e) {
            Log::error('Pengujian koneksi Xendit ditolak oleh API.', [
                'gateway_id' => $setting->id,
                'provider' => $setting->provider,
                'exception' => $e::class,
            ]);

            return new PingConnectionResult(
                success: false,
                message: 'Autentikasi Xendit gagal: '.$e->getMessage(),
                rawResponse: (array) $e->getFullError()
            );
        } catch (Exception $e) {
            Log::error('Pengujian koneksi Xendit gagal terhubung.', [
                'gateway_id' => $setting->id,
                'provider' => $setting->provider,
                'exception' => $e::class,
            ]);

            return new PingConnectionResult(
                success: false,
                message: 'Gagal menghubungi Xendit API: '.$e->getMessage()
            );
        }
    }
}
