<?php

namespace App\Services\PaymentGateway;

use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Models\Invoice;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Koreksi invoice penggabung (Tunggakan Akumulatif) saat tunggakan di dalamnya ternyata dibayar
 * terpisah lewat Pelunasan Susulan (ADR-0069).
 */
class KoreksiInvoicePenggabung
{
    public function __construct(private PaymentGatewayManager $manager) {}

    /**
     * Tunggakan yang dibayar terpisah keluar dari rantai penggabungnya: setiap invoice di rantai
     * memuat nominal tunggakan itu, jadi masing-masing dikurangi agar pelanggan tidak tertagih
     * dua kali (ADR-0069).
     *
     * @param  Collection<int, Invoice>  $rantai
     */
    public function lepaskanDariRantai(Invoice $invoice, Collection $rantai): void
    {
        $nominal = (float) $invoice->jumlah_setelah_promo;

        foreach ($rantai as $penggabung) {
            $terkunci = Invoice::query()->whereKey($penggabung->id)->lockForUpdate()->firstOrFail();
            $terkunci->update([
                'jumlah_setelah_promo' => max(0.0, (float) $terkunci->jumlah_setelah_promo - $nominal),
                'jumlah_tunggakan' => max(0.0, (float) $terkunci->jumlah_tunggakan - $nominal),
            ]);
        }

        $invoice->update([
            'status' => StatusInvoice::MenungguPembayaran,
            'digabung_ke_invoice_id' => null,
        ]);
    }

    /**
     * Link aktif penggabung masih menagih nominal lama: matikan di gateway lalu terbitkan link
     * baru. Bila gagal, link lokal tetap dikosongkan (diterbitkan ulang saat dibutuhkan) dan
     * alasannya dikembalikan untuk dilaporkan.
     */
    public function terbitkanUlangLink(Invoice $penggabung): ?string
    {
        $transaksiAktif = $penggabung->transaksiPaymentGateways()
            ->where('status', StatusTransaksiGateway::Pending)
            ->latest('id')
            ->first();

        if (! $transaksiAktif) {
            return null;
        }

        try {
            $driver = $this->manager->driver($transaksiAktif->gateway ?: XenditDriver::PROVIDER);
            if ($driver instanceof XenditDriver) {
                $driver->kedaluwarsakanInvoice($transaksiAktif, $this->manager->settingUntukTransaksi($transaksiAktif));
            }

            $this->manager->invalidateExpiredInvoice($penggabung, $transaksiAktif);
            $this->manager->buatPaymentLink($penggabung->fresh(), $transaksiAktif->gateway, forceRegenerate: true);

            return null;
        } catch (Throwable $e) {
            Log::error("Pelunasan Susulan: link invoice penggabung {$penggabung->no_invoice} belum diterbitkan ulang: ".$e->getMessage());
            rescue(fn () => $this->manager->invalidateExpiredInvoice($penggabung->fresh(), $transaksiAktif->fresh()), report: false);

            return "Link invoice penggabung {$penggabung->no_invoice} belum diterbitkan ulang ({$e->getMessage()}).";
        }
    }
}
