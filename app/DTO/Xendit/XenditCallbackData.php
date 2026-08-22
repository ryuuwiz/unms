<?php

namespace App\DTO\Xendit;

class XenditCallbackData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $externalId,
        public readonly string $eventType,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $channel,
        public readonly ?string $channelDetail = null,
        public readonly ?string $paymentReference = null,
        public readonly ?string $paidAt = null,
        public readonly array $rawPayload = []
    ) {}

    /**
     * Parse dari berbagai variasi payload webhook Xendit (Modern Payment Request vs Legacy VA/QR).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        // 1. Format Xendit Hosted Invoice Callback (status: PAID / EXPIRED, payment_method, paid_amount, external_id)
        if (isset($payload['status']) && (isset($payload['payment_method']) || isset($payload['paid_amount']) || isset($payload['merchant_name']) || (isset($payload['id']) && isset($payload['external_id']) && ! isset($payload['event'])))) {
            $status = strtoupper((string) ($payload['status'] ?? 'PAID'));
            $pm = strtoupper((string) ($payload['payment_method'] ?? ''));

            $channel = match ($pm) {
                'BANK_TRANSFER', 'VIRTUAL_ACCOUNT' => 'virtual_account',
                'QR_CODE', 'QRIS' => 'qris',
                'EWALLET', 'E_WALLET' => 'ewallet',
                'RETAIL_OUTLET', 'OVER_THE_COUNTER' => 'retail_outlet',
                'CREDIT_CARD', 'CARD' => 'credit_card',
                default => 'invoice',
            };

            $channelDetail = isset($payload['payment_channel']) ? strtolower((string) $payload['payment_channel']) : null;
            $paymentReference = (string) ($payload['payment_destination'] ?? ($payload['payment_id'] ?? ($payload['id'] ?? null)));

            return new self(
                eventId: (string) ($payload['id'] ?? uniqid('inv_evt_', true)),
                externalId: (string) ($payload['external_id'] ?? ''),
                eventType: 'invoice.'.strtolower($status),
                status: $status,
                amount: (float) ($payload['paid_amount'] ?? ($payload['amount'] ?? 0)),
                channel: $channel,
                channelDetail: $channelDetail,
                paymentReference: $paymentReference,
                paidAt: (string) ($payload['paid_at'] ?? ($payload['updated'] ?? now()->toIso8601String())),
                rawPayload: $payload
            );
        }

        // 2. Format Modern Payment Request (event: payment.succeeded / data wrapper)
        if (isset($payload['event']) && isset($payload['data'])) {
            $data = (array) $payload['data'];
            $paymentMethod = (array) ($data['payment_method'] ?? []);
            $pmType = strtoupper((string) ($paymentMethod['type'] ?? ''));

            $channel = match ($pmType) {
                'VIRTUAL_ACCOUNT' => 'virtual_account',
                'QR_CODE' => 'qris',
                'EWALLET' => 'ewallet',
                'OVER_THE_COUNTER' => 'retail_outlet',
                default => 'payment_gateway',
            };

            $channelDetail = null;
            $paymentReference = null;

            if ($channel === 'virtual_account') {
                $va = (array) ($paymentMethod['virtual_account'] ?? []);
                $channelDetail = strtolower((string) ($va['channel_code'] ?? ''));
                $props = (array) ($va['channel_properties'] ?? []);
                $paymentReference = (string) ($props['account_number'] ?? ($va['account_number'] ?? ''));
            } elseif ($channel === 'qris') {
                $qr = (array) ($paymentMethod['qr_code'] ?? []);
                $channelDetail = 'qris';
                $paymentReference = (string) ($qr['channel_properties']['qr_string'] ?? ($data['id'] ?? ''));
            }

            return new self(
                eventId: (string) ($payload['id'] ?? ($data['id'] ?? uniqid('evt_', true))),
                externalId: (string) ($data['reference_id'] ?? ($data['external_id'] ?? '')),
                eventType: (string) ($payload['event'] ?? 'payment.succeeded'),
                status: strtoupper((string) ($data['status'] ?? 'SUCCEEDED')),
                amount: (float) ($data['amount'] ?? 0),
                channel: $channel,
                channelDetail: $channelDetail,
                paymentReference: $paymentReference ?: ((string) ($data['id'] ?? null)),
                paidAt: (string) ($data['updated'] ?? ($data['created'] ?? now()->toIso8601String())),
                rawPayload: $payload
            );
        }

        // 2. Format Legacy VA Callback (misal: payment_id, external_id, bank_code, amount)
        if (isset($payload['bank_code']) || isset($payload['callback_virtual_account_id'])) {
            return new self(
                eventId: (string) ($payload['id'] ?? ($payload['payment_id'] ?? ($payload['callback_virtual_account_id'] ?? uniqid('va_evt_', true)))),
                externalId: (string) ($payload['external_id'] ?? ''),
                eventType: 'virtual_account.paid',
                status: 'SUCCEEDED',
                amount: (float) ($payload['amount'] ?? 0),
                channel: 'virtual_account',
                channelDetail: strtolower((string) ($payload['bank_code'] ?? '')),
                paymentReference: (string) ($payload['account_number'] ?? ($payload['payment_id'] ?? null)),
                paidAt: (string) ($payload['transaction_timestamp'] ?? now()->toIso8601String()),
                rawPayload: $payload
            );
        }

        // 3. Format Legacy QRIS Callback (misal: qr_id, external_id, amount, status: COMPLETED)
        if (isset($payload['qr_id']) || (isset($payload['type']) && strtoupper((string) $payload['type']) === 'DYNAMIC')) {
            return new self(
                eventId: (string) ($payload['id'] ?? ($payload['qr_id'] ?? uniqid('qr_evt_', true))),
                externalId: (string) ($payload['external_id'] ?? ($payload['reference_id'] ?? '')),
                eventType: 'qr.payment',
                status: strtoupper((string) ($payload['status'] ?? 'COMPLETED')),
                amount: (float) ($payload['amount'] ?? 0),
                channel: 'qris',
                channelDetail: 'qris',
                paymentReference: (string) ($payload['qr_id'] ?? null),
                paidAt: (string) ($payload['created'] ?? now()->toIso8601String()),
                rawPayload: $payload
            );
        }

        // 4. Fallback Generic Payload
        return new self(
            eventId: (string) ($payload['id'] ?? uniqid('xnd_evt_', true)),
            externalId: (string) ($payload['external_id'] ?? ($payload['reference_id'] ?? '')),
            eventType: (string) ($payload['event'] ?? 'generic.callback'),
            status: strtoupper((string) ($payload['status'] ?? 'PAID')),
            amount: (float) ($payload['amount'] ?? 0),
            channel: 'payment_gateway',
            channelDetail: null,
            paymentReference: (string) ($payload['id'] ?? null),
            paidAt: now()->toIso8601String(),
            rawPayload: $payload
        );
    }
}
