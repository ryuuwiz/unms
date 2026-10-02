<?php

namespace App\DTO\PaymentGateway;

use App\Enums\AksiPelunasanSusulan;

/**
 * Hasil pengecekan status pembayaran tingkat invoice: Lunas, belum dibayar, atau dilaporkan
 * sebagai Kasus Pelunasan Susulan beserta alasannya (alasan hanya untuk staf, tidak untuk pelanggan).
 */
readonly class HasilCekStatusPembayaran
{
    public function __construct(
        public bool $lunas,
        public ?string $alasanDilaporkan = null,
        public ?string $galat = null,
    ) {}

    public static function dariPelunasanSusulan(HasilPelunasanSusulan $hasil, bool $invoiceLunas): self
    {
        return $hasil->aksi === AksiPelunasanSusulan::Dilaporkan
            ? new self(false, alasanDilaporkan: $hasil->keterangan)
            : new self($invoiceLunas);
    }

    public function dilaporkan(): bool
    {
        return $this->alasanDilaporkan !== null;
    }

    /**
     * @return array{lunas: bool, alasanDilaporkan: string|null, galat: string|null}
     */
    public function toArray(): array
    {
        return ['lunas' => $this->lunas, 'alasanDilaporkan' => $this->alasanDilaporkan, 'galat' => $this->galat];
    }
}
