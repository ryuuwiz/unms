<?php

namespace App\DTO\PaymentGateway;

use App\Enums\GatewayChannel;
use DateTimeInterface;

readonly class PaymentLinkResponse
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $paymentId,
        public string $paymentUrl,
        public string $externalId,
        public float $amount,
        public ?DateTimeInterface $expiredAt = null,
        public GatewayChannel $channel = GatewayChannel::Invoice,
        public ?string $channelDetail = null,
        public ?string $paymentNumber = null,
        public ?string $qrString = null,
        public array $rawResponse = [],
    ) {}
}
