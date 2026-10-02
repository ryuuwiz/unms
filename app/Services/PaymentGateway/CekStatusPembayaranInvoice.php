<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\HasilCekStatusPembayaran;
use App\Models\Invoice;
use App\Models\TransaksiPaymentGateway;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Pengecekan status pembayaran tingkat invoice untuk tombol cek status (staf dan Portal):
 * menanyakan SETIAP transaksi gateway invoice, termasuk link lama, mulai dari yang terbaru.
 * Pembayaran PAID pertama diputuskan oleh Pelunasan Susulan (ADR-0069). Sinkron otomatis
 * saat halaman dibuka tetap memakai `PaymentGatewayManager::sinkronkanStatus` (transaksi terakhir).
 */
class CekStatusPembayaranInvoice
{
    public function __construct(
        private PaymentGatewayManager $manager,
        private PelunasanSusulan $pelunasanSusulan,
        private LaporanPelunasanSusulan $laporan,
    ) {}

    public function periksa(Invoice $invoice): HasilCekStatusPembayaran
    {
        if ($invoice->isLunas()) {
            return new HasilCekStatusPembayaran(true);
        }

        $transaksis = $invoice->transaksiPaymentGateways()->with('pengaturanGateway')->latest('id')->get();

        if ($transaksis->isEmpty()) {
            $this->manager->sinkronkanStatus($invoice);

            return new HasilCekStatusPembayaran($invoice->refresh()->isLunas());
        }

        return $this->periksaTransaksi($invoice, $transaksis);
    }

    /**
     * @param  Collection<int, TransaksiPaymentGateway>  $transaksis  Terbaru lebih dulu.
     */
    private function periksaTransaksi(Invoice $invoice, Collection $transaksis): HasilCekStatusPembayaran
    {
        $galat = null;
        $statusTerbaru = null;

        foreach ($transaksis as $transaksi) {
            try {
                $statusData = $this->manager->cekStatusTransaksi($transaksi);
                $hasil = $this->pelunasanSusulan->tanganiStatusGateway($transaksi, $statusData);
            } catch (Throwable $e) {
                report($e);
                $galat ??= $e->getMessage();

                continue;
            }

            if ($hasil) {
                $this->laporan->laporkan([$hasil]);

                return HasilCekStatusPembayaran::dariPelunasanSusulan($hasil, $invoice->refresh()->isLunas());
            }

            if ($transaksi->is($transaksis->first())) {
                $statusTerbaru = $statusData;
            }
        }

        if ($statusTerbaru !== null) {
            $this->manager->terapkanStatusTransaksi($transaksis->first(), $statusTerbaru);
        }

        return new HasilCekStatusPembayaran($invoice->refresh()->isLunas(), galat: $galat);
    }
}
