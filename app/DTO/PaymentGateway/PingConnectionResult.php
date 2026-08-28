<?php

namespace App\DTO\PaymentGateway;

use Livewire\Wireable;

readonly class PingConnectionResult implements Wireable
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public string $message,
        public ?float $balance = null,
        public string $currency = 'IDR',
        public array $rawResponse = [],
    ) {}

    /**
     * Serialize to array for Livewire state management.
     *
     * @return array<string, mixed>
     */
    public function toLivewire(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'balance' => $this->balance,
            'currency' => $this->currency,
            'rawResponse' => $this->rawResponse,
        ];
    }

    /**
     * Hydrate from Livewire state payload.
     *
     * @param  array<string, mixed>  $value
     */
    public static function fromLivewire($value): static
    {
        return new static(
            success: (bool) ($value['success'] ?? false),
            message: (string) ($value['message'] ?? ''),
            balance: isset($value['balance']) ? (float) $value['balance'] : null,
            currency: (string) ($value['currency'] ?? 'IDR'),
            rawResponse: (array) ($value['rawResponse'] ?? []),
        );
    }
}
