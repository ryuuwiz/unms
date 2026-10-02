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
use Carbon\CarbonInterface;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Xendit\BalanceAndTransaction\BalanceApi;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\CustomerObject;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\InvoiceFee;
use Xendit\Invoice\InvoiceItem;
use Xendit\XenditSdkException;

class XenditDriver extends AbstractPaymentDriver
{
    /** Batas invoice per halaman daftar Xendit (maksimum API). */
    private const BATAS_HALAMAN_DAFTAR = 100;

    public function getProviderName(): string
    {
        return 'xendit';
    }

    public function getProviderLabel(): string
    {
        return 'Xendit Hosted Invoice';
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

    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting, ?string $externalId = null): PaymentLinkResponse
    {
        $apiKey = $this->getApiKey($setting);
        $nominalInvoice = (float) $invoice->jumlah_setelah_promo;
        $fee = $setting->hitungFee('virtual_account', $nominalInvoice);
        $totalTagihan = $nominalInvoice + $fee;

        $invoiceDurationSeconds = $this->hitungDurasiDetik($invoice);
        $expiredAt = Carbon::now()->addSeconds($invoiceDurationSeconds);
        $externalId = $externalId ?: $this->generateExternalId($invoice);

        $pelanggan = $invoice->pelanggan;
        $mobileNumber = self::formatNomorHpE164($pelanggan?->no_hp);
        $payerEmail = (! empty($pelanggan->email) && filter_var($pelanggan->email, FILTER_VALIDATE_EMAIL))
            ? $pelanggan->email
            : null;

        // Line Items
        $items = [];
        $namaPaket = $invoice->layananPelanggan?->paketLayanan->nama_paket ?? 'Langganan Internet';
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

        // Struk gerai retail (Alfamart/Indomaret) mencetak `given_names` sebagai Nama Konsumen,
        // jadi diisi No. Registrasi agar kasir dan admin dapat mencocokkan pembayaran; nama asli
        // tetap tercatat di `surname`.
        $namaLengkap = trim(($pelanggan->nama_depan ?? '').' '.($pelanggan->nama_belakang ?? ''));
        $customerData = [
            'given_names' => ! empty($pelanggan->no_reg) ? $pelanggan->no_reg : ($namaLengkap ?: 'Pelanggan'),
        ];

        if (! empty($pelanggan->no_reg) && $namaLengkap !== '') {
            $customerData['surname'] = $namaLengkap;
        }
        if (! empty($payerEmail)) {
            $customerData['email'] = $payerEmail;
        }
        if (! empty($mobileNumber)) {
            $customerData['mobile_number'] = $mobileNumber;
            $customerData['phone_number'] = $mobileNumber;
        }

        $customerObj = new CustomerObject($customerData);
        // Tautan Tagihan, bukan /tagihan/{id}: pelanggan yang membayar tanpa login harus bisa
        // kembali ke halaman tagihannya setelah membayar (ADR-0067).
        $redirectUrl = $invoice->tautanTagihan();

        // Mock (tanpa panggilan API sama sekali) HANYA boleh aktif di environment 'testing' --
        // satu-satunya nilai APP_ENV yang tidak pernah bisa muncul di server sungguhan (hanya
        // di-set oleh phpunit.xml). 'local' SENGAJA tidak lagi termasuk: developer lokal yang
        // sudah mengonfigurasi XENDIT_SECRET_KEY asli (mode test Xendit, prefix xnd_development_)
        // berhak mendapat panggilan API sungguhan ke sandbox Xendit -- bukan URL palsu yang
        // tidak pernah bisa dibuka. PengaturanGateway.sandbox_mode juga BUKAN sinyal yang tepat
        // di sini: field itu berarti "pakai kredensial mode test Xendit", bukan "jangan pernah
        // hubungi Xendit sama sekali" -- percobaan sebelumnya memakainya untuk hal itu salah
        // kaprah (lihat ADR 0039) dan sekaligus membuat link palsu tetap bisa lolos ke produksi
        // kalau APP_ENV salah baca sebagai 'local'. Dengan hanya 'testing' yang dipercaya, APP_ENV
        // yang salah konfigurasi di produksi tidak lagi bisa memicu mock sama sekali -- sistem
        // akan mencoba panggilan API sungguhan (berhasil jika key valid, gagal keras dan tercatat
        // jika tidak), bukan diam-diam menyerahkan URL palsu ke pelanggan.
        $isSandboxEnv = app()->environment('testing');

        if (empty($apiKey) && ! $isSandboxEnv) {
            throw new Exception('Xendit Secret Key belum dikonfigurasi. Payment link tidak dapat diterbitkan.');
        }

        try {
            if (! $isSandboxEnv) {
                $invoiceApi = $this->getInvoiceApi($apiKey);
                $params = new CreateInvoiceRequest([
                    'external_id' => $externalId,
                    'amount' => $totalTagihan,
                    'payer_email' => $payerEmail,
                    'description' => app(DeskripsiTagihanBuilder::class)->buat($invoice),
                    'invoice_duration' => (float) $invoiceDurationSeconds,
                    'customer' => $customerObj,
                    'items' => $items,
                    'fees' => $fees,
                    'success_redirect_url' => $redirectUrl,
                    'failure_redirect_url' => $redirectUrl,
                    'currency' => 'IDR',
                ]);

                $response = $invoiceApi->createInvoice(
                    create_invoice_request: $params
                );

                $responseArray = json_decode((string) json_encode($response), true) ?: [];
                $paymentId = (string) $response->getId();
                $paymentUrl = (string) $response->getInvoiceUrl();
            } else {
                // Mock fallback untuk testing lokal
                $paymentId = 'inv_mock_'.uniqid();
                $paymentUrl = 'https://checkout-staging.xendit.co/v2/'.$paymentId;
                $responseArray = [
                    'mock' => true,
                    'id' => $paymentId,
                    'invoice_url' => $paymentUrl,
                    'status' => 'PENDING',
                    'external_id' => $externalId,
                    'amount' => $totalTagihan,
                ];
            }

            return new PaymentLinkResponse(
                paymentId: $paymentId,
                paymentUrl: $paymentUrl,
                externalId: $externalId,
                amount: $totalTagihan,
                expiredAt: $expiredAt,
                channel: GatewayChannel::Invoice,
                channelDetail: 'hosted_invoice',
                rawResponse: $responseArray
            );
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

    public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
    {
        // Transaksi hanya dicek dengan referensinya sendiri: meminjam ID invoice terkini akan
        // mengecek transaksi lama terhadap invoice Xendit milik transaksi lain (ADR-0067).
        // Tanpa referensi, transaksi dicari lewat external_id-nya.
        $xenditId = $target instanceof Invoice
            ? ($target->payment_gateway_id ?: $target->xendit_invoice_id)
            : ($target->xendit_reference_id ?: $target->provider_reference_id);

        $apiKey = $this->getApiKey($setting);

        // Sama seperti createPaymentLink() -- hanya 'testing' yang dipercaya, lihat ADR 0039.
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

        // 1. Jika ID bertipe V3 Payment Request (pr-xxx / py-xxx), panggil endpoint V3
        if (str_starts_with($xenditId, 'pr-') || str_starts_with($xenditId, 'py-') || str_starts_with($xenditId, 'pr_')) {
            return $this->checkPaymentRequestV3Status($xenditId, $apiKey);
        }

        // 2. Coba periksa via Invoice API (V1/V2)
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
     * Semua invoice PAID/SETTLED satu Koneksi yang dibayar dalam rentang waktu, untuk Pelunasan
     * Susulan (lihat CONTEXT.md). Dibaca lewat HTTP langsung karena model SDK membuang `paid_at`,
     * `paid_amount`, dan `payment_channel`. Rentang dipecah per hari dan kursor `last_invoice`
     * hanya dipakai saat satu hari penuh; paginasi berhenti begitu halaman tidak membawa invoice
     * baru, sehingga tetap aman bila Xendit mengabaikan kursornya.
     *
     * @return list<PaymentCallbackData>
     *
     * @throws RuntimeException bila kredensial kosong atau Xendit menolak permintaan
     */
    public function daftarPembayaranLunas(PengaturanGateway $setting, CarbonInterface $sejak, ?CarbonInterface $sampai = null): array
    {
        $apiKey = $this->getApiKey($setting);
        if (empty($apiKey)) {
            throw new RuntimeException("Koneksi {$setting->nama} belum memiliki Xendit Secret Key.");
        }

        $sampai ??= now();
        $hasil = [];

        for ($awal = Carbon::parse($sejak)->startOfDay(); $awal->lessThan($sampai); $awal = $awal->copy()->addDay()) {
            $akhir = $awal->copy()->addDay()->min($sampai);
            $kursor = null;

            do {
                $halaman = $this->ambilHalamanInvoiceLunas($apiKey, $awal, $akhir, $kursor);
                $baru = array_filter($halaman, fn (array $invoice): bool => ! isset($hasil[$invoice['id'] ?? '']));

                foreach ($baru as $invoice) {
                    $hasil[(string) $invoice['id']] = $this->petakanPayload($invoice);
                }

                $kursor = $halaman === [] ? null : (string) (end($halaman)['id'] ?? '');
            } while (count($halaman) >= self::BATAS_HALAMAN_DAFTAR && $baru !== [] && $kursor !== '');
        }

        return array_values($hasil);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ambilHalamanInvoiceLunas(string $apiKey, CarbonInterface $awal, CarbonInterface $akhir, ?string $kursor): array
    {
        // Parameter array dikirim berulang (statuses=PAID&statuses=SETTLED), sama seperti SDK resmi.
        $query = implode('&', array_filter([
            'statuses=PAID&statuses=SETTLED',
            'limit='.self::BATAS_HALAMAN_DAFTAR,
            'paid_after='.rawurlencode($awal->toIso8601ZuluString()),
            'paid_before='.rawurlencode($akhir->toIso8601ZuluString()),
            $kursor ? 'last_invoice='.rawurlencode($kursor) : null,
        ]));

        $response = Http::withBasicAuth($apiKey, '')
            ->timeout(30)
            ->get("https://api.xendit.co/v2/invoices?{$query}");

        if (! $response->successful()) {
            throw new RuntimeException("Gagal mengambil daftar invoice PAID dari Xendit: HTTP {$response->status()} {$response->body()}");
        }

        $data = $response->json();

        return array_values(array_filter(is_array($data) ? $data : [], 'is_array'));
    }

    /**
     * Matikan invoice Xendit milik transaksi agar link lamanya tidak bisa dibayar lagi
     * (mis. setelah nominal invoice dikoreksi oleh Pelunasan Susulan, ADR-0069).
     *
     * @throws RuntimeException bila Xendit menolak permintaan
     */
    public function kedaluwarsakanInvoice(TransaksiPaymentGateway $transaksi, PengaturanGateway $setting): void
    {
        $xenditId = $transaksi->xendit_reference_id ?: $transaksi->provider_reference_id;
        if (empty($xenditId)) {
            return;
        }

        $response = Http::withBasicAuth($this->getApiKey($setting), '')
            ->timeout(20)
            ->post('https://api.xendit.co/invoices/'.rawurlencode($xenditId).'/expire!');

        if (! $response->successful()) {
            throw new RuntimeException("Gagal mematikan invoice Xendit {$xenditId}: HTTP {$response->status()} {$response->body()}");
        }
    }

    /**
     * Petakan payload invoice/pembayaran Xendit (callback maupun hasil daftar invoice).
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
            // Dipakai untuk penegakan strict-IDR di ProcessPaymentWebhookJob (ADR 0028 §2).
            currency: $this->mataUangDari($payload),
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
            in_array($statusStr, ['SUCCEEDED', 'PAID', 'SETTLED', 'CAPTURED', 'BERHASIL'], true) => 'PAID',
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
            ?? ($payload['amount']
            ?? ($payload['data']['capture_amount']
            ?? ($payload['data']['amount'] ?? 0)));

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
            ?? ($payload['data']['id'] ?? null)
            ?? ($payload['data']['payment_request_id'] ?? null)
            ?? ($payload['data']['payment_id'] ?? null)
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
