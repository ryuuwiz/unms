<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\Enums\AksiPelunasanSusulan;
use App\Enums\StatusWebhookLog;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;

/**
 * Webhook pembayaran untuk invoice Digabung/Dibatalkan diputuskan oleh Pelunasan Susulan, sama
 * seperti sapuan harian (ADR-0069).
 */
class WebhookPelunasanSusulan
{
    public function __construct(
        private PelunasanSusulan $pelunasanSusulan,
        private LaporanPelunasanSusulan $laporan,
    ) {}

    /**
     * Invoice Dibatalkan (soft-deleted) tidak terlihat oleh pencarian invoice biasa di webhook.
     */
    public function untukInvoiceDibatalkan(PaymentCallbackData $callbackData): bool
    {
        return $callbackData->isPaid()
            && ! $callbackData->isTest
            && (bool) $this->pelunasanSusulan->cariTarget($callbackData)->invoice?->trashed();
    }

    /**
     * Yang dilunasi menutup Log Webhook sebagai Diproses dan tercatat di audit trail tanpa
     * notifikasi per webhook. Yang dilaporkan ditutup sebagai Diabaikan beserta alasannya -- bukan
     * Gagal, agar rekonsiliasi berkala tidak men-dispatch ulang tanpa akhir; laporannya muncul
     * lewat `pembayaran:cek-lunas-xendit`.
     */
    public function tangani(PaymentCallbackData $callbackData, WebhookLog $webhookLog, ?TransaksiPaymentGateway $transaksi): void
    {
        $hasil = $this->pelunasanSusulan->tangani($callbackData, $transaksi?->pengaturanGateway);

        $selesai = in_array($hasil->aksi, [AksiPelunasanSusulan::Dilunasi, AksiPelunasanSusulan::SudahTercatat], true);
        $webhookLog->update([
            'status_proses' => $selesai ? StatusWebhookLog::Diproses : StatusWebhookLog::Diabaikan,
            'catatan_error' => $hasil->keterangan ?: null,
        ]);

        if ($hasil->aksi === AksiPelunasanSusulan::Dilunasi) {
            $this->laporan->laporkan([$hasil], kabariAdmin: false);
        }
    }
}
