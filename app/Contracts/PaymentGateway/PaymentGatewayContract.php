<?php

namespace App\Contracts\PaymentGateway;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    /**
     * Dapatkan kode unik provider (e.g. 'xendit', 'ipaymu').
     */
    public function getProviderName(): string;

    /**
     * Dapatkan nama label provider (e.g. 'Xendit Hosted Invoice', 'iPaymu API v2').
     */
    public function getProviderLabel(): string;

    /**
     * Buat payment link / hosted checkout URL untuk tagihan invoice.
     */
    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting): PaymentLinkResponse;

    /**
     * Cek status transaksi pembayaran langsung ke API gateway.
     *
     * @return array<string, mixed>
     */
    public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array;

    /**
     * Verifikasi keaslian webhook signature/token dari request callback.
     */
    public function verifyWebhook(Request $request, PengaturanGateway $setting): bool;

    /**
     * Normalisasi payload webhook menjadi DTO PaymentCallbackData.
     */
    public function parseWebhookPayload(Request $request): PaymentCallbackData;

    /**
     * Cek koneksi API dan validitas kredensial (Ping / Saldo).
     */
    public function pingConnection(PengaturanGateway $setting): PingConnectionResult;
}
