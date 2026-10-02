<?php

namespace App\DTO\PaymentGateway;

/**
 * Hasil pengecekan status pembayaran tingkat invoice: Lunas, belum dibayar, atau sudah dibayar
 * di gateway tetapi perlu diproses manual beserta alasannya (alasan hanya untuk staf, tidak untuk pelanggan).
 */
readonly class HasilCekStatusPembayaran
{
    public function __construct(
        public bool $lunas,
        public ?string $alasanManual = null,
        public ?string $galat = null,
    ) {}

    public function perluDiprosesManual(): bool
    {
        return $this->alasanManual !== null;
    }

    /**
     * @return array{lunas: bool, alasanManual: string|null, galat: string|null}
     */
    public function toArray(): array
    {
        return ['lunas' => $this->lunas, 'alasanManual' => $this->alasanManual, 'galat' => $this->galat];
    }
}
