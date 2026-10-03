<?php

namespace App\Contracts\PaymentGateway;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
use App\DTO\PaymentGateway\PingConnectionResult;
use App\Enums\GatewayChannel;
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
     * Generate format external ID unik per sesi pembuatan invoice.
     *
     * Dipanggil oleh PaymentGatewayManager::buatPaymentLink() SEBELUM memanggil
     * createPaymentLink(), agar baris TransaksiPaymentGateway dapat direservasi lokal
     * lebih dulu dengan external_id yang pasti sama dengan yang dikirim ke gateway.
     */
    public function generateExternalId(Invoice $invoice): string;

    /**
     * Buat payment link / hosted checkout URL untuk tagihan invoice.
     *
     * @param  string|null  $externalId  External ID yang sudah direservasi lokal oleh manager.
     *                                   Jika null, driver membangkitkan external_id sendiri
     *                                   (kompatibilitas mundur untuk pemanggilan langsung).
     * @param  GatewayChannel|null  $metode  Metode bayar yang boleh dipakai di checkout. Null untuk driver yang
     *                                       checkout-nya memuat semua metode dan menghitung biayanya sendiri.
     * @param  int  $biayaAdmin  Biaya Admin Gateway yang ditambahkan ke nominal tagihan (ADR-0072).
     */
    public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting, ?string $externalId = null, ?GatewayChannel $metode = null, int $biayaAdmin = 0): PaymentLinkResponse;

    /**
     * Apakah halaman checkout gateway menghitung Biaya Admin Gateway per kanal sendiri (satu link untuk semua
     * metode). Jika tidak, pelanggan memilih metode lebih dulu dan tiap metode mendapat link sendiri (ADR-0073).
     */
    public function menghitungBiayaSendiri(): bool;

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
