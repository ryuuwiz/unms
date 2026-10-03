<?php

namespace Tests\Support;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\GatewayChannel;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\Drivers\AbstractPaymentDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Driver gateway palsu untuk menguji manager, job, dan controller webhook tanpa terikat format
 * gateway sungguhan. Callback diverifikasi lewat header `x-callback-token` = kredensial
 * `callback_token` koneksi; payload memakai field sederhana (external_id, status, paid_amount, ...).
 * Subclass menimpa checkStatus() untuk mensimulasikan jawaban gateway.
 */
class GatewayUjiDriver extends AbstractPaymentDriver
{
    public const PROVIDER = 'uji';

    public function getProviderName(): string
    {
        return self::PROVIDER;
    }

    public function getProviderLabel(): string
    {
        return 'Gateway Uji';
    }

    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting, ?string $externalId = null): PaymentLinkResponse
    {
        $id = 'uji_'.uniqid();

        return new PaymentLinkResponse(
            paymentId: $id,
            paymentUrl: 'https://gateway-uji.test/bayar/'.$id,
            externalId: $externalId ?? $this->generateExternalId($invoice),
            amount: (float) $invoice->jumlah_setelah_promo,
            expiredAt: Carbon::now()->addSeconds($this->hitungDurasiDetik($invoice)),
        );
    }

    public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
    {
        return ['status' => 'PENDING'];
    }

    public function verifyWebhook(Request $request, PengaturanGateway $setting): bool
    {
        $token = (string) $setting->getCredential('callback_token', '');

        return $token !== '' && hash_equals($token, (string) $request->header('x-callback-token'));
    }

    public function parseWebhookPayload(Request $request): PaymentCallbackData
    {
        $payload = $request->all();
        $externalId = (string) ($payload['external_id'] ?? '');

        return new PaymentCallbackData(
            provider: self::PROVIDER,
            externalId: $externalId,
            status: strtoupper((string) ($payload['status'] ?? 'PENDING')),
            paidAmount: (float) ($payload['paid_amount'] ?? ($payload['amount'] ?? 0)),
            eventId: isset($payload['id']) ? (string) $payload['id'] : null,
            paidAt: (string) ($payload['paid_at'] ?? now()->toIso8601String()),
            channel: match (strtoupper((string) ($payload['payment_method'] ?? ''))) {
                'VIRTUAL_ACCOUNT', 'BANK_TRANSFER' => GatewayChannel::VirtualAccount,
                'QR_CODE', 'QRIS' => GatewayChannel::Qris,
                default => GatewayChannel::Invoice,
            },
            channelDetail: isset($payload['payment_channel']) ? strtolower((string) $payload['payment_channel']) : null,
            paymentReference: isset($payload['id']) ? (string) $payload['id'] : null,
            isTest: str_contains($externalId, 'test-'),
            rawPayload: $payload,
            currency: isset($payload['currency']) ? strtoupper((string) $payload['currency']) : null,
        );
    }

    public function pingConnection(PengaturanGateway $setting): PingConnectionResult
    {
        return new PingConnectionResult(success: true, message: 'Gateway uji terhubung.');
    }
}
