<?php

namespace App\DTO\PaymentGateway;

use App\Enums\GatewayChannel;

readonly class PaymentCallbackData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $status, // PAID, SETTLED, EXPIRED, FAILED, PENDING
        public float $paidAmount,
        public ?string $eventId = null,
        public ?string $paidAt = null,
        public GatewayChannel $channel = GatewayChannel::Invoice,
        public ?string $channelDetail = null,
        public ?string $paymentReference = null,
        public bool $isTest = false,
        public array $rawPayload = [],
        public ?string $currency = null,
    ) {}

    public function isPaid(): bool
    {
        return in_array(strtoupper($this->status), ['PAID', 'SETTLED', 'SUCCEEDED', 'BERHASIL'], true);
    }

    public function isExpired(): bool
    {
        return in_array(strtoupper($this->status), ['EXPIRED', 'KEDALUWARSA'], true);
    }

    public function isFailed(): bool
    {
        return in_array(strtoupper($this->status), ['FAILED', 'GAGAL', 'CANCELLED'], true);
    }
}
