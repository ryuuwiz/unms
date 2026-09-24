<?php

namespace App\Services\Xendit;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\Xendit\XenditCallbackData;
use App\Enums\GatewayChannel;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class XenditPaymentService
{
    public function __construct(
        protected PaymentGatewayManager $manager
    ) {}

    /**
     * Terbitkan link pembayaran Xendit Hosted Invoice untuk tagihan tertentu.
     */
    public function buatInvoice(Invoice $invoice, bool $forceRegenerate = false): TransaksiPaymentGateway
    {
        return $this->manager->buatPaymentLink($invoice, 'xendit', $forceRegenerate);
    }

    /**
     * Alias untuk kompatibilitas mundur.
     */
    public function buatVirtualAccount(Invoice $invoice, string $bankCode = 'BCA'): TransaksiPaymentGateway
    {
        return $this->buatInvoice($invoice);
    }

    /**
     * Alias untuk kompatibilitas mundur.
     */
    public function buatQris(Invoice $invoice): TransaksiPaymentGateway
    {
        return $this->buatInvoice($invoice);
    }

    /**
     * Sinkronisasikan status invoice langsung dengan API gateway.
     *
     * @return array<string, mixed>
     */
    public function sinkronkanStatus(Invoice $invoice): array
    {
        return $this->manager->sinkronkanStatus($invoice);
    }

    /**
     * Eksekusi transaksi pelunasan invoice di database.
     *
     * @param  array<string, mixed>|XenditCallbackData|PaymentCallbackData  $payloadOrData
     */
    public function prosesPelunasanDariXendit(
        Invoice $invoice,
        array|XenditCallbackData|PaymentCallbackData $payloadOrData,
        ?TransaksiPaymentGateway $transaksi = null,
        ?WebhookLog $webhookLog = null
    ): bool {
        if ($payloadOrData instanceof XenditCallbackData) {
            $data = new PaymentCallbackData(
                provider: 'xendit',
                externalId: $payloadOrData->externalId,
                status: $payloadOrData->status,
                paidAmount: $payloadOrData->amount,
                eventId: $payloadOrData->eventId,
                paidAt: $payloadOrData->paidAt,
                channel: GatewayChannel::tryFrom((string) $payloadOrData->channel) ?? GatewayChannel::Invoice,
                channelDetail: $payloadOrData->channelDetail,
                paymentReference: $payloadOrData->paymentReference,
                isTest: $payloadOrData->isTestDummy(),
                rawPayload: $payloadOrData->rawPayload
            );
        } else {
            $data = $payloadOrData;
        }

        return $this->manager->prosesPelunasan($invoice, $data, $transaksi, $webhookLog);
    }

    /**
     * Cek status invoice ke gateway API.
     *
     * @return array<string, mixed>
     */
    public function cekStatusInvoice(Invoice|TransaksiPaymentGateway $target): array
    {
        $setting = PengaturanGateway::getXenditSetting();
        $driver = $this->manager->driver('xendit');

        return $driver->checkStatus($target, $setting);
    }

    /**
     * Cek status transaksi pembayaran ke gateway API.
     *
     * @return array<string, mixed>
     */
    public function cekStatusTransaksi(TransaksiPaymentGateway $transaksi): array
    {
        return $this->cekStatusInvoice($transaksi);
    }

    /**
     * Cek koneksi dan validitas API Key.
     *
     * @return array<string, mixed>
     */
    public function cekKoneksiApi(): array
    {
        $setting = PengaturanGateway::getXenditSetting();
        $res = $this->manager->pingConnection($setting);

        return [
            'success' => $res->success,
            'message' => $res->message,
            'balance' => $res->balance,
            'currency' => $res->currency,
        ];
    }

    /**
     * Simulasikan pembayaran di sandbox.
     *
     * @return array<string, mixed>
     */
    public function simulasikanPembayaran(TransaksiPaymentGateway $transaksi): array
    {
        return $this->simulasikanWebhookLokal($transaksi);
    }

    /**
     * Simulasikan penerimaan Webhook lokal secara internal tanpa butuh tunnel publik.
     *
     * @return array<string, mixed>
     */
    public function simulasikanWebhookLokal(TransaksiPaymentGateway|Invoice $target): array
    {
        $setting = PengaturanGateway::getXenditSetting();
        $token = (string) ($setting->getCredential('callback_token') ?: config('services.xendit.callback_token'));

        if (empty($token)) {
            $token = 'test_webhook_token';
        }

        if ($target instanceof Invoice) {
            $invoice = $target;
            $transaksi = $invoice->transaksiPaymentGatewayAktif();
        } else {
            $transaksi = $target;
            $invoice = $transaksi->invoice;
        }

        // Invoice bisa sudah di-soft-delete/dibatalkan sementara transaksi gateway-nya masih ada.
        if ($invoice === null) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => 'Invoice transaksi ini sudah dihapus; simulasi webhook tidak dapat dijalankan.',
                'response' => [],
            ];
        }

        $xenditId = $invoice->payment_gateway_id ?: ($invoice->xendit_invoice_id ?: ($transaksi?->xendit_reference_id ?: 'sim_inv_'.uniqid()));
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
            'payer_email' => $invoice->pelanggan?->email,
            'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
            'payment_method' => 'BANK_TRANSFER',
            'payment_channel' => 'BCA',
            'payment_destination' => '880812345678',
            'paid_at' => Carbon::now()->toIso8601String(),
            'created' => Carbon::now()->subMinutes(5)->toIso8601String(),
            'updated' => Carbon::now()->toIso8601String(),
        ];

        $rawJson = json_encode($payload) ?: '{}';
        $request = Request::create(
            uri: '/webhook/payment/xendit',
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

        /** @var PaymentWebhookController $controller */
        $controller = app(PaymentWebhookController::class);
        $response = $controller->handle($request, 'xendit');
        $responseData = json_decode((string) $response->getContent(), true) ?: [];

        $isSuccess = $response->getStatusCode() === 200;

        return [
            'success' => $isSuccess,
            'status_code' => $response->getStatusCode(),
            'message' => $isSuccess ? 'Simulasi webhook lokal berhasil diproses.' : 'Webhook lokal gagal diproses.',
            'response' => $responseData,
        ];
    }
}
