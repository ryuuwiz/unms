<?php

namespace App\DTO\PaymentGateway;

use App\Enums\AksiPelunasanSusulan;

/**
 * Hasil pengecekan status pembayaran tingkat invoice: Lunas, belum dibayar, atau dilaporkan
 * sebagai Kasus Pelunasan Susulan beserta alasannya (alasan hanya untuk staf).
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
     * Ringkasan untuk staf, termasuk alasan internal kasus yang dilaporkan.
     *
     * @return array{judul: string, keterangan: string|null, warna: string}
     */
    public function untukStaf(): array
    {
        return match (true) {
            $this->lunas => ['judul' => 'Lunas', 'keterangan' => 'Pembayaran ditemukan di Xendit dan tagihan sudah Lunas.', 'warna' => 'green'],
            $this->dilaporkan() => ['judul' => 'Dilaporkan untuk tindakan manual', 'keterangan' => $this->alasanDilaporkan, 'warna' => 'amber'],
            $this->galat !== null => ['judul' => 'Belum dibayar / sebagian gagal dicek', 'keterangan' => "Sebagian link gagal dicek ke Xendit: {$this->galat}", 'warna' => 'red'],
            default => ['judul' => 'Belum dibayar', 'keterangan' => 'Tidak ada link pembayaran invoice ini yang lunas di Xendit.', 'warna' => 'zinc'],
        };
    }

    /**
     * Pesan untuk pelanggan di Portal: kasus yang dilaporkan tidak menampilkan alasan internal.
     */
    public function pesanPelanggan(): string
    {
        return match (true) {
            $this->lunas => 'Pembayaran berhasil terkonfirmasi! Tagihan telah lunas.',
            $this->dilaporkan() => 'Pembayaran Anda sedang kami periksa. Tim kami akan menghubungi Anda.',
            default => 'Status tagihan: Menunggu pembayaran.',
        };
    }
}
