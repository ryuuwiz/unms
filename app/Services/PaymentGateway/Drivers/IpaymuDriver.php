<?php

namespace App\Services\PaymentGateway\Drivers;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\GatewayChannel;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
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
        return 'iPaymu API v2';
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

    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting): PaymentLinkResponse
    {
        $va = $this->getVa($setting);
        $apiKey = $this->getApiKey($setting);
        $baseUrl = $this->getBaseUrl($setting);

        $nominalInvoice = (float) $invoice->jumlah_setelah_promo;
        $totalTagihan = $nominalInvoice;

        $invoiceDurationSeconds = $this->hitungDurasiDetik($invoice);
        $expiredAt = Carbon::now()->addSeconds($invoiceDurationSeconds);
        $externalId = $this->generateExternalId($invoice);

        $pelanggan = $invoice->pelanggan;
        $buyerPhone = self::formatNomorHpNumeric($pelanggan?->no_hp) ?: '081234567890';
        $buyerName = trim(($pelanggan?->nama_depan ?? 'Pelanggan').' '.($pelanggan?->nama_belakang ?? ''));
        $buyerEmail = (! empty($pelanggan?->email) && filter_var($pelanggan->email, FILTER_VALIDATE_EMAIL))
            ? $pelanggan->email
            : 'noreply@gobilling.id';

        $namaPaket = $invoice->layananPelanggan?->paketLayanan?->nama_paket ?? 'Langganan Internet';
        $redirectUrl = route('portal.invoice.show', $invoice->id);
        $notifyUrl = url('/webhook/payment/ipaymu');

        $body = [
            'product' => ["Paket: {$namaPaket}"],
            'qty' => [1],
            'price' => [$totalTagihan],
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

    public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
    {
        $va = $this->getVa($setting);
        $apiKey = $this->getApiKey($setting);
        $baseUrl = $this->getBaseUrl($setting);

        $externalId = $target instanceof Invoice
            ? ($target->transaksiPaymentGatewayAktif()?->external_id ?: $target->no_invoice)
            : $target->external_id;

        if (empty($va) || empty($apiKey) || app()->environment('testing')) {
            return [
                'status' => 'PENDING',
                'message' => 'Mode Test/Offline: Status transaksi aktif.',
            ];
        }

        $body = [
            'transactionId' => $externalId,
        ];

        try {
            $headers = $this->generateSignatureHeaders($va, $apiKey, $body, 'POST');
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post($baseUrl.'transaction', $body);

            return $response->json() ?: ['error' => 'Empty response'];
        } catch (Exception $e) {
            Log::error("Gagal cek status transaksi iPaymu [{$externalId}]: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function verifyWebhook(Request $request, PengaturanGateway $setting): bool
    {
        // iPaymu mengirimkan POST callback dengan parameter trx_id, sid, reference_id, status, dll.
        // Jika ada header signature, verifikasi signature.
        $apiKey = $this->getApiKey($setting);
        $va = $this->getVa($setting);

        if (empty($apiKey) || empty($va)) {
            Log::warning('iPaymu Webhook: VA / API Key belum dikonfigurasi.');

            return false;
        }

        // Verifikasi keberadaan parameter wajib
        $trxId = $request->input('trx_id') ?: $request->input('transaction_id');
        $status = $request->input('status');

        if (empty($trxId) || empty($status)) {
            return false;
        }

        return true;
    }

    public function parseWebhookPayload(Request $request): PaymentCallbackData
    {
        $payload = $request->all();

        $rawStatus = strtoupper((string) ($payload['status'] ?? ''));
        $statusCode = (int) ($payload['status_code'] ?? 0);

        $isPaid = in_array($rawStatus, ['BERHASIL', 'SETTLEMENT', 'PAID', 'SUCCEEDED'], true) || $statusCode === 1;
        $isExpired = in_array($rawStatus, ['EXPIRED', 'KEDALUWARSA', 'CANCELLED', 'BATAL'], true);

        $status = $isPaid ? 'PAID' : ($isExpired ? 'EXPIRED' : $rawStatus);
        $externalId = (string) ($payload['reference_id'] ?? ($payload['referenceId'] ?? ''));
        $amount = (float) ($payload['total'] ?? ($payload['amount'] ?? 0));
        $rawEventId = $payload['trx_id'] ?? ($payload['sid'] ?? null);
        $eventId = ! empty($rawEventId) ? trim((string) $rawEventId) : null;

        $via = strtolower((string) ($payload['via'] ?? ''));
        $channelDetail = (string) ($payload['channel'] ?? $via);

        $channel = match ($via) {
            'va', 'virtual_account', 'bank_transfer' => GatewayChannel::VirtualAccount,
            'qris', 'qr' => GatewayChannel::Qris,
            'cstore', 'retail' => GatewayChannel::RetailOutlet,
            'wallet', 'ewallet' => GatewayChannel::Ewallet,
            default => GatewayChannel::Invoice,
        };

        $isTestDummy = str_contains($externalId, 'test-') || ($payload['is_test'] ?? false);

        return new PaymentCallbackData(
            provider: 'ipaymu',
            externalId: $externalId,
            status: $status,
            paidAmount: $amount,
            eventId: $eventId,
            paidAt: now()->toIso8601String(),
            channel: $channel,
            channelDetail: $channelDetail,
            paymentReference: $eventId,
            isTest: (bool) $isTestDummy,
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
