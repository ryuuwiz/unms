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

    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting): PaymentLinkResponse
    {
        $apiKey = $this->getApiKey($setting);
        $nominalInvoice = (float) $invoice->jumlah_setelah_promo;
        $fee = $setting->hitungFee('virtual_account', $nominalInvoice);
        $totalTagihan = $nominalInvoice + $fee;

        $invoiceDurationSeconds = $this->hitungDurasiDetik($invoice);
        $expiredAt = Carbon::now()->addSeconds($invoiceDurationSeconds);
        $externalId = $this->generateExternalId($invoice);

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

        try {
            if (! empty($apiKey) && ! app()->environment('testing')) {
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

        if (empty($apiKey) || app()->environment('testing')) {
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
            $invoiceApi = $this->getInvoiceApi($apiKey);
            $response = $invoiceApi->getInvoiceById($xenditId);

            return json_decode((string) json_encode($response), true) ?: [];
        } catch (Exception $e) {
            Log::error("Gagal cek status invoice Xendit {$xenditId}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function verifyWebhook(Request $request, PengaturanGateway $setting): bool
    {
        $headerToken = $request->header('x-callback-token');
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
        $pm = strtoupper((string) ($payload['payment_method'] ?? ''));

        $channel = match ($pm) {
            'BANK_TRANSFER', 'VIRTUAL_ACCOUNT' => GatewayChannel::VirtualAccount,
            'QR_CODE', 'QRIS' => GatewayChannel::Qris,
            'EWALLET', 'E_WALLET' => GatewayChannel::Ewallet,
            'RETAIL_OUTLET' => GatewayChannel::RetailOutlet,
            default => GatewayChannel::Invoice,
        };

        $externalId = (string) ($payload['external_id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? 'PENDING'));
        $amount = (float) ($payload['paid_amount'] ?? ($payload['amount'] ?? 0));
        $eventId = (string) ($payload['id'] ?? null);
        $paidAt = (string) ($payload['paid_at'] ?? ($payload['updated'] ?? now()->toIso8601String()));
        $channelDetail = isset($payload['payment_channel']) ? strtolower((string) $payload['payment_channel']) : null;
        $paymentRef = (string) ($payload['payment_destination'] ?? ($payload['payment_id'] ?? ($payload['id'] ?? null)));

        $isTestDummy = str_contains($externalId, '123124123') || str_contains($externalId, 'test-') || ($payload['is_test'] ?? false);

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
            rawPayload: $payload
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
