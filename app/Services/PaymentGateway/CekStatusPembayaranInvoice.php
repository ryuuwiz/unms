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
 * PAID pertama melunasi invoice lewat sinkron biasa; invoice Digabung/Dibatalkan tidak
 * dilunasi otomatis dan diserahkan ke staf. Sinkron otomatis saat halaman dibuka tetap
 * memakai `PaymentGatewayManager::sinkronkanStatus` (transaksi terakhir).
 */
class CekStatusPembayaranInvoice
{
    public function __construct(private PaymentGatewayManager $manager) {}

    public function periksa(Invoice $invoice): HasilCekStatusPembayaran
    {
        if ($invoice->isLunas()) {
            return new HasilCekStatusPembayaran(true);
        }

        $transaksis = $invoice->transaksiPaymentGateways()->gatewayTerdaftar()->with('pengaturanGateway')->latest('id')->get();

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
            } catch (Throwable $e) {
                report($e);
                $galat ??= $e->getMessage();

                continue;
            }

            if (in_array(strtoupper((string) ($statusData['status'] ?? '')), ['PAID', 'SETTLED', 'SUCCEEDED', 'BERHASIL'], true)) {
                return $this->tanganiPaid($invoice, $transaksi, $statusData);
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

    /**
     * @param  array<string, mixed>  $statusData
     */
    private function tanganiPaid(Invoice $invoice, TransaksiPaymentGateway $transaksi, array $statusData): HasilCekStatusPembayaran
    {
        if ($invoice->isDigabung() || $invoice->isDibatalkan() || $invoice->trashed()) {
            return new HasilCekStatusPembayaran(false, alasanManual: "Link {$transaksi->external_id} sudah dibayar di gateway, tetapi invoice berstatus {$invoice->status->label()}: periksa dan proses manual.");
        }

        // Validasi Ketat Nominal Gateway, sama dengan webhook: selisih sekecil apa pun diproses manual.
        $nominalTagihan = (int) round((float) $transaksi->total_tagihan);
        $nominalDibayar = (int) round((float) ($statusData['paid_amount'] ?? ($statusData['amount'] ?? 0)));
        if ($nominalDibayar !== $nominalTagihan) {
            return new HasilCekStatusPembayaran(false, alasanManual: 'Nominal tidak sama: dibayar Rp '.number_format($nominalDibayar, 0, ',', '.').', seharusnya Rp '.number_format($nominalTagihan, 0, ',', '.').'. Periksa dan proses manual.');
        }

        $this->manager->terapkanStatusTransaksi($transaksi, $statusData);

        return new HasilCekStatusPembayaran($invoice->refresh()->isLunas());
    }
}
