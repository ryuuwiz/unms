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
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function getProviderName(): string
    {
        return 'xendit';
    }

    public function getProviderLabel(): string
    {
        return 'Xendit Hosted Invoice';
    }

    /**
     * Dapatkan API Key Xendit dari database setting atau fallback config.
     */
    protected function getApiKey(PengaturanGateway $setting): string
    {
        return (string) ($setting->getCredential('secret_key') ?: config('services.xendit.secret_key', ''));
    }

    /**
     * Dapatkan Callback Token Xendit dari database setting atau fallback config.
     */
    protected function getCallbackToken(PengaturanGateway $setting): string
    {
        return (string) ($setting->getCredential('callback_token') ?: config('services.xendit.callback_token', ''));
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
        $payerEmail = (! empty($pelanggan?->email) && filter_var($pelanggan->email, FILTER_VALIDATE_EMAIL))
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
            'given_names' => ! empty($pelanggan?->nama_depan) ? trim($pelanggan->nama_depan) : 'Pelanggan',
        ];

        if (! empty($pelanggan?->nama_belakang)) {
            $customerData['surname'] = trim($pelanggan->nama_belakang);
        }
        if (! empty($payerEmail)) {
            $customerData['email'] = $payerEmail;
        }
        if (! empty($mobileNumber)) {
            $customerData['mobile_number'] = $mobileNumber;
            $customerData['phone_number'] = $mobileNumber;
        }

        $customerObj = new CustomerObject($customerData);
        $redirectUrl = route('portal.invoice.show', $invoice->id);

        // Mock hanya boleh aktif di local/testing. Di environment lain (termasuk production),
        // API key kosong adalah kesalahan konfigurasi dan harus gagal keras -- bukan diam-diam
        // menyerahkan URL checkout-staging.xendit.co palsu ke pelanggan.
        $isSandboxEnv = app()->environment(['local', 'testing']);

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
                    'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
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
        $xenditId = $target instanceof Invoice
            ? ($target->payment_gateway_id ?: $target->xendit_invoice_id)
            : ($target->xendit_reference_id ?: ($target->invoice?->payment_gateway_id ?: $target->invoice?->xendit_invoice_id));

        $apiKey = $this->getApiKey($setting);

        if (app()->environment(['local', 'testing'])) {
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
            // Fallback coba periksa ke V3 Payment Requests API jika invoice ID tidak ditemukan
            try {
                $v3Result = $this->checkPaymentRequestV3Status($xenditId, $apiKey);
                if (! empty($v3Result) && ! isset($v3Result['error'])) {
                    return $v3Result;
                }
            } catch (Exception) {
                // Abaikan error fallback
            }

            Log::error("Gagal cek status invoice Xendit {$xenditId}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
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
        $payload = $request->all();

        // 1. Ekstrak Event Name (V3 format: 'event' e.g. 'payment.succeeded', 'payment_request.succeeded')
        $eventName = strtolower(trim((string) ($payload['event'] ?? '')));

        // 2. Tangani Payment Method baik berupa String (v1 / invoice) maupun Array/Object (v2 / v3 payment_requests)
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
        } elseif (is_string($rawPm)) {
            $pm = strtoupper(trim($rawPm));
        } else {
            $pm = '';
        }

        if (empty($channelDetail)) {
            $rawChannel = $payload['payment_channel']
                ?? ($payload['data']['payment_channel']
                ?? ($payload['data']['channel_code']
                ?? ($payload['channel_code'] ?? null)));

            if (is_string($rawChannel)) {
                $channelDetail = strtolower(trim($rawChannel));
            } elseif (is_array($rawChannel)) {
                $channelDetail = strtolower((string) ($rawChannel['channel_code'] ?? ($rawChannel['code'] ?? '')));
            }
        }

        $channel = match ($pm) {
            'BANK_TRANSFER', 'VIRTUAL_ACCOUNT' => GatewayChannel::VirtualAccount,
            'QR_CODE', 'QRIS', 'QR' => GatewayChannel::Qris,
            'EWALLET', 'E_WALLET' => GatewayChannel::Ewallet,
            'RETAIL_OUTLET', 'OVER_THE_COUNTER' => GatewayChannel::RetailOutlet,
            default => GatewayChannel::Invoice,
        };

        // 3. External / Reference ID
        $rawExternalId = $payload['external_id']
            ?? ($payload['data']['reference_id']
            ?? ($payload['data']['external_id']
            ?? ($payload['reference_id'] ?? '')));
        $externalId = is_string($rawExternalId) ? trim($rawExternalId) : (is_numeric($rawExternalId) ? (string) $rawExternalId : '');

        // 4. Normalisasi Status Transaksi dari Event V3 maupun status attribute
        $rawStatus = $payload['status'] ?? ($payload['data']['status'] ?? '');
        $statusStr = is_string($rawStatus) ? strtoupper(trim($rawStatus)) : '';

        if (! empty($eventName)) {
            $status = match (true) {
                str_contains($eventName, 'succeeded'), str_contains($eventName, 'paid'), str_contains($eventName, 'capture'), str_contains($eventName, 'settled') => 'PAID',
                str_contains($eventName, 'failure'), str_contains($eventName, 'failed'), str_contains($eventName, 'declined') => 'FAILED',
                str_contains($eventName, 'expired'), str_contains($eventName, 'cancelled') => 'EXPIRED',
                default => in_array($statusStr, ['SUCCEEDED', 'PAID', 'SETTLED', 'CAPTURED', 'BERHASIL'], true) ? 'PAID' : (in_array($statusStr, ['FAILED', 'FAILURE', 'DECLINED'], true) ? 'FAILED' : (in_array($statusStr, ['EXPIRED', 'CANCELLED'], true) ? 'EXPIRED' : 'PENDING')),
            };
        } else {
            $status = match (true) {
                in_array($statusStr, ['SUCCEEDED', 'PAID', 'SETTLED', 'CAPTURED', 'BERHASIL'], true) => 'PAID',
                in_array($statusStr, ['FAILED', 'FAILURE', 'DECLINED', 'GAGAL'], true) => 'FAILED',
                in_array($statusStr, ['EXPIRED', 'CANCELLED', 'KEDALUWARSA'], true) => 'EXPIRED',
                default => 'PENDING',
            };
        }

        // 5. Nominal Transaksi
        $rawAmount = $payload['paid_amount']
            ?? ($payload['amount']
            ?? ($payload['data']['capture_amount']
            ?? ($payload['data']['amount'] ?? 0)));
        $amount = is_numeric($rawAmount) ? (float) $rawAmount : 0.0;

        // 6. Identifier Event / Payment Request ID / Payment ID
        $rawEventId = $payload['id']
            ?? ($payload['data']['id'] ?? null)
            ?? ($payload['data']['payment_request_id'] ?? null)
            ?? ($payload['data']['payment_id'] ?? null)
            ?? ($payload['payment_id'] ?? null)
            ?? ($payload['event_id'] ?? null)
            ?? ($payload['callback_virtual_account_id'] ?? null)
            ?? ($payload['qr_id'] ?? null);

        $eventId = (is_string($rawEventId) || is_numeric($rawEventId)) && ! empty($rawEventId) ? trim((string) $rawEventId) : null;

        // 7. Waktu Pembayaran / Update
        $rawPaidAt = $payload['paid_at']
            ?? ($payload['updated']
            ?? ($payload['data']['updated']
            ?? ($payload['created']
            ?? ($payload['data']['created'] ?? null))));
        $paidAt = is_string($rawPaidAt) ? $rawPaidAt : now()->toIso8601String();

        // 8. Payment Reference (URL redirect actions / VA / QR / ID)
        $paymentRef = null;
        if (isset($payload['data']['actions']) && is_array($payload['data']['actions'])) {
            foreach ($payload['data']['actions'] as $action) {
                if (isset($action['url']) && is_string($action['url'])) {
                    $paymentRef = $action['url'];
                    break;
                }
            }
        }
        if (empty($paymentRef)) {
            $rawPaymentRef = $payload['payment_destination']
                ?? ($payload['payment_id']
                ?? ($payload['id']
                ?? ($payload['data']['id'] ?? null)));
            $paymentRef = (is_string($rawPaymentRef) || is_numeric($rawPaymentRef)) ? (string) $rawPaymentRef : null;
        }

        $isTestDummy = str_contains($externalId, '123124123')
            || str_contains($externalId, 'test-')
            || ($payload['is_test'] ?? false)
            || ($payload['data']['is_test'] ?? false);

        // 9. Mata Uang -- dipakai untuk penegakan strict-IDR di ProcessPaymentWebhookJob (ADR 0028 §2).
        $rawCurrency = $payload['currency'] ?? ($payload['data']['currency'] ?? null);
        $currency = is_string($rawCurrency) && $rawCurrency !== '' ? strtoupper(trim($rawCurrency)) : null;

        return new PaymentCallbackData(
            provider: 'xendit',
            externalId: $externalId,
            status: $status,
            paidAmount: $amount,
            eventId: $eventId,
            paidAt: $paidAt,
            channel: $channel,
            channelDetail: $channelDetail,
            paymentReference: $paymentRef,
            isTest: (bool) $isTestDummy,
            rawPayload: $payload,
            currency: $currency
        );
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
            $amount = (float) ($balanceData['balance'] ?? ($balance->getBalance() ?? 0));

            return new PingConnectionResult(
                success: true,
                message: 'Koneksi ke Xendit API berhasil terhubung.',
                balance: $amount,
                currency: 'IDR',
                rawResponse: $balanceData
            );
        } catch (XenditSdkException $e) {
            return new PingConnectionResult(
                success: false,
                message: 'Autentikasi Xendit gagal: '.$e->getMessage(),
                rawResponse: (array) $e->getFullError()
            );
        } catch (Exception $e) {
            return new PingConnectionResult(
                success: false,
                message: 'Gagal menghubungi Xendit API: '.$e->getMessage()
            );
        }
    }
}
