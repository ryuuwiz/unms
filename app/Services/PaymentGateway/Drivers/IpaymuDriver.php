<?php

namespace App\Services\PaymentGateway\Drivers;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\GatewayChannel;
use App\Models\ChannelPembayaran;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\DeskripsiTagihanBuilder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpaymuDriver extends AbstractPaymentDriver
{
    public function getProviderName(): string
    {
        return 'ipaymu';
    }

    public function getProviderLabel(): string
    {
        return 'iPaymu Hosted Invoice';
    }

    /**
     * Dapatkan Virtual Account / Merchant ID iPaymu.
     */
    protected function getVa(PengaturanGateway $setting): string
    {
        return (string) ($setting->getCredential('va') ?: config('services.ipaymu.va', ''));
    }

    /**
     * Dapatkan API Key iPaymu.
     */
    protected function getApiKey(PengaturanGateway $setting): string
    {
        return (string) ($setting->getCredential('api_key') ?: config('services.ipaymu.api_key', ''));
    }

    /**
     * Dapatkan Base URL iPaymu berdasarkan mode sandbox.
     */
    protected function getBaseUrl(PengaturanGateway $setting): string
    {
        return $setting->sandbox_mode
            ? 'https://sandbox.ipaymu.com/api/v2/'
            : 'https://my.ipaymu.com/api/v2/';
    }

    /**
     * Generate Header Signature untuk request iPaymu v2.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    public function generateSignatureHeaders(string $va, string $apiKey, array $body, string $method = 'POST'): array
    {
        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES) ?: '{}';
        $bodyHash = strtolower(hash('sha256', $jsonBody));
        $stringToSign = strtoupper($method).':'.$va.':'.$bodyHash.':'.$apiKey;
        $signature = hash_hmac('sha256', $stringToSign, $apiKey);
        $timestamp = Carbon::now()->format('YmdHis');

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'va' => $va,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];
    }

    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting, ?string $externalId = null): PaymentLinkResponse
    {
        $va = $this->getVa($setting);
        $apiKey = $this->getApiKey($setting);
        $baseUrl = $this->getBaseUrl($setting);

        $nominalInvoice = (float) $invoice->jumlah_setelah_promo;
        $totalTagihan = $nominalInvoice;

        $invoiceDurationSeconds = $this->hitungDurasiDetik($invoice);
        $expiredAt = Carbon::now()->addSeconds($invoiceDurationSeconds);
        $externalId = $externalId ?: $this->generateExternalId($invoice);

        ['nama' => $buyerName, 'email' => $buyerEmail, 'hp' => $buyerPhone] = $this->dataPembeli($invoice);

        $namaPaket = $invoice->layananPelanggan?->paketLayanan->nama_paket ?? 'Langganan Internet';
        // Tautan Tagihan, bukan /tagihan/{id}: pelanggan yang membayar tanpa login harus bisa
        // kembali ke halaman tagihannya setelah membayar (ADR-0067).
        $redirectUrl = $invoice->tautanTagihan();
        $notifyUrl = url('/webhook/payment/ipaymu');

        $body = [
            'product' => ["Paket: {$namaPaket}"],
            'qty' => [1],
            'price' => [(int) round($totalTagihan)],
            'description' => [app(DeskripsiTagihanBuilder::class)->buat($invoice)],
            'returnUrl' => $redirectUrl,
            'cancelUrl' => $redirectUrl,
            'notifyUrl' => $notifyUrl,
            'referenceId' => $externalId,
            'buyerName' => $buyerName,
            'buyerEmail' => $buyerEmail,
            'buyerPhone' => $buyerPhone,
            'feeDirection' => $setting->bebankan_ke_pelanggan ? 'BUYER' : 'MERCHANT',
            'expired' => round($invoiceDurationSeconds / 3600, 1), // Durasi jam
        ];

        try {
            if (! empty($va) && ! empty($apiKey) && ! app()->environment('testing')) {
                $headers = $this->generateSignatureHeaders($va, $apiKey, $body, 'POST');
                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->post($baseUrl.'payment', $body);

                if (! $response->successful()) {
                    throw new Exception("HTTP {$response->status()}: {$response->body()}");
                }

                $data = $response->json();
                if (($data['Status'] ?? 0) !== 200 && ($data['status'] ?? 0) !== 200) {
                    $msg = $data['Message'] ?? ($data['message'] ?? 'Gagal membuat sesi iPaymu.');
                    throw new Exception($msg);
                }

                $responseData = $data['Data'] ?? ($data['data'] ?? []);
                $sessionId = (string) ($responseData['SessionID'] ?? ($responseData['session_id'] ?? uniqid('ipm_')));
                $paymentUrl = (string) ($responseData['Url'] ?? ($responseData['url'] ?? ''));
                $responseArray = $data;
            } else {
                // Mock fallback untuk testing lokal
                $sessionId = 'ipm_sess_'.uniqid();
                $paymentUrl = 'https://sandbox.ipaymu.com/payment/'.$sessionId;
                $responseArray = [
                    'Status' => 200,
                    'Message' => 'success',
                    'Data' => [
                        'SessionID' => $sessionId,
                        'Url' => $paymentUrl,
                    ],
                ];
            }

            return new PaymentLinkResponse(
                paymentId: $sessionId,
                paymentUrl: $paymentUrl,
                externalId: $externalId,
                amount: $totalTagihan,
                expiredAt: $expiredAt,
                channel: GatewayChannel::Invoice,
                channelDetail: 'hosted_payment',
                rawResponse: $responseArray
            );
        } catch (Exception $e) {
            Log::error("Gagal membuat Payment Link iPaymu untuk Invoice [{$invoice->no_invoice}]: ".$e->getMessage());
            throw new Exception('Gagal membuat Payment Link iPaymu: '.$e->getMessage());
        }
    }

    /**
     * Tipe Channel lokal -> `paymentMethod` iPaymu.
     */
    private const METODE = [
        'virtual_account' => 'va',
        'qris' => 'qris',
        'retail_outlet' => 'cstore',
    ];

    public function kodeChannel(): array
    {
        return [
            GatewayChannel::VirtualAccount->value => ['bag', 'bca', 'bpd_bali', 'bni', 'cimb', 'mandiri', 'bmi', 'bri', 'bsi', 'permata', 'danamon', 'btn'],
            GatewayChannel::Qris->value => ['mpm'],
            GatewayChannel::RetailOutlet->value => ['alfamart', 'indomaret'],
        ];
    }

    /**
     * `GET /api/v2/payment-channels`, hanya channel berstatus active dari tipe yang didukung.
     */
    public function daftarChannelGateway(PengaturanGateway $setting): array
    {
        $va = $this->getVa($setting);
        $apiKey = $this->getApiKey($setting);

        // Dokumentasi iPaymu menyebut body GET = "{}" mentah, tetapi sandbox hanya menerima
        // SHA-256 dari "{}" -- aturan yang sama dengan POST (diverifikasi 2026-10-03).
        $bodyHash = hash('sha256', '{}');
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'va' => $va,
            'signature' => hash_hmac('sha256', "GET:{$va}:{$bodyHash}:{$apiKey}", $apiKey),
            'timestamp' => Carbon::now()->format('YmdHis'),
        ])->timeout(8)->get($this->getBaseUrl($setting).'payment-channels');

        if (! $response->successful() || (int) $response->json('Status') !== 200) {
            throw new Exception("iPaymu payment-channels gagal: HTTP {$response->status()} ".($response->json('Message') ?? ''));
        }

        $tipePerMetode = array_flip(self::METODE);
        $hasil = [];
        foreach ((array) $response->json('Data') as $metode) {
            $tipe = $tipePerMetode[$metode['Code'] ?? ''] ?? null;
            if ($tipe === null) {
                continue;
            }

            foreach ((array) ($metode['Channels'] ?? []) as $channel) {
                if (($channel['FeatureStatus'] ?? 'active') !== 'active') {
                    continue;
                }

                $fee = (array) ($channel['TransactionFee'] ?? []);
                $flat = strtoupper((string) ($fee['ActualFeeType'] ?? 'FLAT')) === 'FLAT';
                $hasil[] = [
                    'tipe' => $tipe,
                    'kode' => strtolower((string) $channel['Code']),
                    'nama' => (string) ($channel['Name'] ?? $channel['Code']),
                    'logo' => $channel['Logo'] ?? null,
                    'fee' => (float) ($fee['ActualFee'] ?? 0) + ($flat ? (float) ($fee['AdditionalFee'] ?? 0) : 0),
                    'fee_persen' => ! $flat,
                ];
            }
        }

        return $hasil;
    }

    /**
     * Direct Payment (`/api/v2/payment/direct`): nomor VA, kode bayar, atau QR dikembalikan
     * langsung dan ditampilkan di portal. Fee Admin sudah ada di `amount`, jadi iPaymu tidak
     * boleh menambah fee lagi ke pembeli (ADR-0073).
     */
    public function createChannelPayment(Invoice $invoice, PengaturanGateway $setting, ChannelPembayaran $channel, string $externalId, float $amount): PaymentLinkResponse
    {
        ['nama' => $nama, 'email' => $email, 'hp' => $hp] = $this->dataPembeli($invoice);
        $jam = max(1, (int) ceil($this->hitungDurasiDetik($invoice) / 3600));
        $nominal = (int) round($amount);
        $namaPaket = $invoice->layananPelanggan?->paketLayanan->nama_paket ?? 'Langganan Internet';

        $data = $this->kirim($setting, 'payment/direct', [
            'name' => $nama,
            'phone' => $hp,
            'email' => $email,
            'amount' => $nominal,
            'notifyUrl' => url('/webhook/payment/ipaymu'),
            'expired' => $jam,
            'expiredType' => 'hours',
            'comments' => app(DeskripsiTagihanBuilder::class)->buat($invoice),
            'referenceId' => $externalId,
            'paymentMethod' => self::METODE[$channel->tipe->value],
            'paymentChannel' => $channel->kode,
            'product' => ["Paket: {$namaPaket}"],
            'qty' => [1],
            'price' => [$nominal],
            'feeDirection' => 'MERCHANT',
        ]);

        $isQris = $channel->tipe === GatewayChannel::Qris;
        // ponytail: contoh respons resmi hanya untuk VA (PaymentNo); nama field QR belum
        // terdokumentasi, jadi QrString lalu PaymentNo dicoba. Url iPaymu tetap disimpan sebagai cadangan.
        $qrString = $isQris ? ($data['QrString'] ?? ($data['PaymentNo'] ?? null)) : null;

        $kedaluwarsa = self::waktuWib($data['Expired'] ?? null);

        return new PaymentLinkResponse(
            paymentId: (string) ($data['TransactionId'] ?? ''),
            paymentUrl: (string) ($data['Url'] ?? ''),
            externalId: $externalId,
            // Callback dicocokkan lewat sub_total = nominal yang kita kirim, bukan Total iPaymu.
            amount: (float) $nominal,
            expiredAt: $kedaluwarsa ? Carbon::parse($kedaluwarsa) : Carbon::now()->addHours($jam),
            channel: $channel->tipe,
            channelDetail: $channel->kode,
            paymentNumber: $isQris ? null : ($data['PaymentNo'] ?? null),
            qrString: $qrString !== null ? (string) $qrString : null,
            rawResponse: $data,
        );
    }

    /**
     * @return array{nama: string, email: string, hp: string}
     */
    private function dataPembeli(Invoice $invoice): array
    {
        $pelanggan = $invoice->pelanggan;
        $email = (string) ($pelanggan->email ?? '');

        return [
            'nama' => trim(($pelanggan->nama_depan ?? 'Pelanggan').' '.($pelanggan->nama_belakang ?? '')),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'noreply@gobilling.id',
            'hp' => self::formatNomorHpNumeric($pelanggan?->no_hp) ?: '081234567890',
        ];
    }

    /**
     * Cek status ke iPaymu. `/transaction` butuh `trx_id` iPaymu, yang baru diketahui setelah
     * callback pertama; sebelum itu transaksi dicari lewat `/history` berdasarkan ReferenceId.
     */
    public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
    {
        $transaksi = $target instanceof Invoice ? $target->transaksiPaymentGatewayAktif() : $target;
        if (! $transaksi) {
            return ['error' => 'Invoice belum memiliki transaksi iPaymu.'];
        }

        if (empty($this->getVa($setting)) || empty($this->getApiKey($setting))) {
            return ['error' => 'Virtual Account (Merchant ID) atau API Key iPaymu belum diisi.'];
        }

        try {
            $trxId = $transaksi->provider_reference_id;
            $data = is_numeric($trxId)
                ? $this->kirim($setting, 'transaction', ['transactionId' => (string) $trxId])
                : $this->cariDiRiwayat($setting, $transaksi);
        } catch (Exception $e) {
            Log::error("Gagal cek status transaksi iPaymu [{$transaksi->external_id}]: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }

        if ($data === null) {
            return ['error' => 'Transaksi iPaymu belum ditemukan untuk referensi ini.'];
        }

        return [
            'id' => (string) ($data['TransactionId'] ?? ''),
            'status' => self::petakanStatus((int) ($data['Status'] ?? 0)),
            // SubTotal, bukan Amount: lihat catatan sub_total di parseWebhookPayload.
            'paid_amount' => (float) ($data['SubTotal'] ?? 0),
            'paid_at' => self::waktuWib($data['SuccessDate'] ?? null),
            'raw' => $data,
        ];
    }

    /**
     * Konfirmasi callback berstatus lunas langsung ke `/transaction` sebelum invoice dilunasi
     * (ADR-0072): HMAC callback saja tidak cukup untuk jalur yang menyangkut uang.
     *
     * @throws Exception Bila API iPaymu tidak dapat dihubungi -- job webhook akan mencoba lagi.
     */
    public function konfirmasiPembayaran(PaymentCallbackData $callback, PengaturanGateway $setting): bool
    {
        if (! is_numeric($callback->paymentReference)) {
            return false;
        }

        $data = $this->kirim($setting, 'transaction', ['transactionId' => (string) $callback->paymentReference]);
        $referensi = $data['ReferenceId'] ?? null;

        return self::petakanStatus((int) ($data['Status'] ?? 0)) === 'PAID'
            && ($referensi === null || (string) $referensi === $callback->externalId);
    }

    /**
     * ponytail: memindai maksimal 5 halaman x 20 transaksi sejak transaksi dibuat; perluas bila
     * volume harian iPaymu melebihi 100 transaksi.
     *
     * @return array<string, mixed>|null
     */
    private function cariDiRiwayat(PengaturanGateway $setting, TransaksiPaymentGateway $transaksi): ?array
    {
        $cocok = [];
        for ($page = 1; $page <= 5; $page++) {
            $data = $this->kirim($setting, 'history', [
                'date' => 'created_at',
                'startdate' => ($transaksi->created_at ?? now())->format('Y-m-d'),
                'enddate' => now()->format('Y-m-d'),
                'page' => $page,
                'limit' => 20,
                'orderBy' => 'id',
                'order' => 'DESC',
            ]);

            foreach ((array) ($data['Transaction'] ?? []) as $hasil) {
                if ((string) ($hasil['ReferenceId'] ?? '') === $transaksi->external_id) {
                    $cocok[] = $hasil;
                }
            }

            if ($page >= (int) ($data['Pagination']['total_pages'] ?? 1)) {
                break;
            }
        }

        // Satu sesi hosted bisa punya beberapa transaksi (mis. QRIS kedaluwarsa lalu bayar VA).
        usort($cocok, fn (array $a, array $b) => (self::petakanStatus((int) $b['Status']) === 'PAID') <=> (self::petakanStatus((int) $a['Status']) === 'PAID'));

        return $cocok[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function kirim(PengaturanGateway $setting, string $endpoint, array $body): array
    {
        $headers = $this->generateSignatureHeaders($this->getVa($setting), $this->getApiKey($setting), $body, 'POST');
        $response = Http::withHeaders($headers)->timeout(15)->post($this->getBaseUrl($setting).$endpoint, $body);

        if (! $response->successful() || (int) $response->json('Status') !== 200) {
            throw new Exception("iPaymu {$endpoint} gagal: HTTP {$response->status()} ".($response->json('Message') ?? ''));
        }

        return (array) $response->json('Data');
    }

    /**
     * iPaymu mengirim waktu lokal WIB tanpa zona (`YYYY-MM-DD HH:MM:SS`). Dikembalikan dalam UTC:
     * Eloquent menyimpan Carbon apa adanya tanpa konversi zona, jadi offset +07:00 akan tersimpan
     * sebagai jam UTC dan menggeser expired_at / dibayar_pada 7 jam.
     */
    private static function waktuWib(mixed $waktu): ?string
    {
        return is_string($waktu) && $waktu !== '' ? Carbon::parse($waktu, 'Asia/Jakarta')->utc()->toIso8601String() : null;
    }

    /**
     * Kode status transaksi iPaymu: 1 berhasil, 6 berhasil belum settle, -2 kedaluwarsa,
     * 2/4/5 batal/error/gagal, 0 pending.
     */
    public static function petakanStatus(int $kode): string
    {
        return match ($kode) {
            1, 6 => 'PAID',
            -2 => 'EXPIRED',
            2, 4, 5 => 'FAILED',
            default => 'PENDING',
        };
    }

    /**
     * Verifikasi `X-Signature` callback iPaymu: HMAC-SHA256 atas JSON body yang key-nya
     * diurutkan, dengan Nomor VA merchant sebagai kunci. Body form-urlencoded (default
     * dashboard iPaymu) harus dinormalisasi tipenya dulu agar JSON-nya identik dengan milik
     * iPaymu. Body mentah dipakai, bukan $request->all(): middleware Laravel mengubah ""
     * menjadi null dan memangkas spasi sehingga hash tidak cocok.
     */
    public function verifyWebhook(Request $request, PengaturanGateway $setting): bool
    {
        $va = $this->getVa($setting);
        $signature = (string) $request->header('X-Signature', '');

        if ($va === '' || $signature === '') {
            return false;
        }

        $raw = $request->getContent();
        if ($request->isJson()) {
            $data = (array) json_decode($raw, true);
        } else {
            parse_str($raw, $data);
            $data = self::normalisasiForm($data);
        }

        $signature = (string) ($data['signature'] ?? $signature);
        unset($data['signature']);
        ksort($data);

        return hash_equals(hash_hmac('sha256', (string) json_encode($data), $va), $signature);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalisasiForm(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = match (true) {
                $key === 'is_escrow' => in_array($value, ['1', 'true'], true),
                in_array($key, ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'], true) => (int) $value,
                $key === 'additional_info' => $value === '[]' ? [] : $value,
                default => is_array($value) ? $value : (string) $value,
            };
        }

        $data['additional_info'] ??= [];

        return $data;
    }

    public function parseWebhookPayload(Request $request): PaymentCallbackData
    {
        $payload = $request->all();

        $rawStatus = strtoupper((string) ($payload['status'] ?? ''));
        $statusCode = (int) ($payload['status_code'] ?? 0);
        $status = $statusCode === 1 || $rawStatus === 'BERHASIL' ? 'PAID' : self::petakanStatus($statusCode);

        $externalId = (string) ($payload['reference_id'] ?? ($payload['referenceId'] ?? ''));
        $trxId = isset($payload['trx_id']) && $payload['trx_id'] !== '' ? (string) $payload['trx_id'] : null;

        $via = strtolower((string) ($payload['via'] ?? ''));
        $channel = match ($via) {
            'va', 'virtual_account', 'bank_transfer' => GatewayChannel::VirtualAccount,
            'qris', 'qr' => GatewayChannel::Qris,
            'cstore', 'retail' => GatewayChannel::RetailOutlet,
            'wallet', 'ewallet' => GatewayChannel::Ewallet,
            default => GatewayChannel::Invoice,
        };

        return new PaymentCallbackData(
            provider: 'ipaymu',
            externalId: $externalId,
            status: $status,
            // sub_total = nominal tagihan; total sudah termasuk fee bila feeDirection BUYER.
            paidAmount: (float) ($payload['sub_total'] ?? ($payload['total'] ?? 0)),
            // Satu trx_id mengirim beberapa callback (pending lalu berhasil): status ikut jadi
            // bagian event id agar callback lunas tidak dianggap duplikat oleh webhook_log.
            eventId: $trxId !== null ? "{$trxId}-{$statusCode}" : null,
            paidAt: self::waktuWib($payload['paid_at'] ?? null) ?? now()->toIso8601String(),
            channel: $channel,
            channelDetail: (string) ($payload['channel'] ?? $via),
            paymentReference: $trxId,
            isTest: str_contains($externalId, 'test-') || (bool) ($payload['is_test'] ?? false),
            rawPayload: $payload
        );
    }

    public function pingConnection(PengaturanGateway $setting): PingConnectionResult
    {
        $va = $this->getVa($setting);
        $apiKey = $this->getApiKey($setting);
        $baseUrl = $this->getBaseUrl($setting);

        if (app()->environment('testing')) {
            return new PingConnectionResult(
                success: true,
                message: 'Koneksi ke iPaymu API berhasil terhubung (Mock Test).',
                balance: 5000000.0,
                currency: 'IDR'
            );
        }

        if (empty($va) || empty($apiKey)) {
            return new PingConnectionResult(
                success: false,
                message: 'Virtual Account (Merchant ID) atau API Key iPaymu belum diisi.'
            );
        }

        $body = ['account' => $va];

        try {
            $headers = $this->generateSignatureHeaders($va, $apiKey, $body, 'POST');
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post($baseUrl.'balance', $body);

            if (! $response->successful()) {
                return new PingConnectionResult(
                    success: false,
                    message: "Gagal terhubung ke iPaymu API: HTTP {$response->status()}"
                );
            }

            $data = $response->json();
            if (($data['Status'] ?? 0) !== 200 && ($data['status'] ?? 0) !== 200) {
                $msg = $data['Message'] ?? ($data['message'] ?? 'Autentikasi iPaymu gagal.');

                return new PingConnectionResult(
                    success: false,
                    message: "Autentikasi iPaymu gagal: {$msg}",
                    rawResponse: $data
                );
            }

            $balanceData = $data['Data'] ?? ($data['data'] ?? []);
            $amount = (float) ($balanceData['MerchantBalance'] ?? ($balanceData['merchant_balance'] ?? 0));

            return new PingConnectionResult(
                success: true,
                message: 'Koneksi ke iPaymu API berhasil terhubung.',
                balance: $amount,
                currency: 'IDR',
                rawResponse: $data
            );
        } catch (Exception $e) {
            return new PingConnectionResult(
                success: false,
                message: 'Gagal menghubungi iPaymu API: '.$e->getMessage()
            );
        }
    }
}
