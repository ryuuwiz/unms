<?php

namespace App\Actions\LayananPelanggan;

use App\Enums\MasaAktifSatuan;
use App\Enums\StatusLayanan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use Illuminate\Support\Carbon;

class PerpanjangMasaAktifAction
{
    /**
     * Perpanjang masa aktif layanan pelanggan atas pelunasan sebuah invoice (PRD 4.2).
     *
     * Aturan tunggal: akumulatif dari `tanggal_expired` bila sisa masa aktif masih di masa
     * depan, apa pun status layanannya saat ini (termasuk Suspend) -- sisa masa aktif yang
     * belum terpakai tidak pernah hangus. Baru direset dari tanggal bayar bila masa aktif
     * sudah lewat. Sebelumnya ada dua implementasi berbeda: jalur pembayaran manual
     * (BillingService) memakai status === Aktif sebagai syarat akumulasi, sedangkan jalur
     * payment gateway (PaymentGatewayManager) memakai isFuture(). Keduanya kini disatukan
     * di sini memakai aturan isFuture().
     *
     * Pemanggil bertanggung jawab mengunci baris $layanan (lockForUpdate) dan membungkus
     * pemanggilan ini dalam transaksi database miliknya sendiri -- action ini tidak membuka
     * transaksi baru agar tetap atomic bersama mutasi invoice/pembayaran di pemanggil.
     */
    public function execute(LayananPelanggan $layanan, Invoice $invoice, Carbon $dibayarPada): LayananPelanggan
    {
        $paket = $layanan->paketLayanan;
        $masaNilai = $paket ? (int) $paket->masa_aktif_nilai : 1;
        $masaSatuan = $paket ? $paket->masa_aktif_satuan : MasaAktifSatuan::Bulan;

        // Tambahan bonus bulan dari promo bila ada
        $promo = $invoice->promo;
        $bonusBulan = ($promo && $promo->bonus_bulan) ? (int) $promo->bonus_bulan : 0;

        $currentExpired = $layanan->tanggal_expired ? Carbon::parse($layanan->tanggal_expired) : null;

        $baseDate = ($currentExpired && $currentExpired->isFuture())
            ? $currentExpired->copy()
            : $dibayarPada->copy()->startOfDay();

        $newExpired = $masaSatuan === MasaAktifSatuan::Bulan
            ? $baseDate->addMonths($masaNilai + $bonusBulan)
            : $baseDate->addDays($masaNilai);

        $layanan->update([
            'tanggal_expired' => $newExpired->toDateString(),
            'status' => StatusLayanan::Aktif,
        ]);

        return $layanan;
    }
}
