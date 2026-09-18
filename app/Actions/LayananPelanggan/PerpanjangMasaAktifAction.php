<?php

namespace App\Actions\LayananPelanggan;

use App\Enums\MasaAktifSatuan;
use App\Enums\StatusLayanan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PengaturanSiklusTagihan;
use Illuminate\Support\Carbon;

class PerpanjangMasaAktifAction
{
    /**
     * Perpanjang masa aktif layanan pelanggan atas pelunasan sebuah invoice (PRD 4.2).
     *
     * Paket berdurasi bulan: masa aktif bertambah sebanyak siklus yang dicakup invoice (invoice itu
     * sendiri ditambah invoice periodik lama yang digabung ke dalamnya -- Tunggakan Akumulatif),
     * dihitung dari `tanggal_expired` lama dan tidak pernah dari tanggal bayar, lalu disesuaikan ke
     * Hari Jatuh Tempo pada Siklus Tagihan. Bulan saat layanan diisolir tetap ditagih.
     *
     * Paket berdurasi hari tidak mengenal siklus: akumulatif dari `tanggal_expired` bila masih di
     * masa depan, selain itu dari tanggal bayar.
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

        if ($masaSatuan === MasaAktifSatuan::Bulan) {
            $siklus = 1 + $invoice->invoiceDigabung()->count();
            $tanggalBaru = ($currentExpired ?? $dibayarPada->copy()->startOfDay())
                ->addMonthsNoOverflow(($masaNilai * $siklus) + $bonusBulan);

            $newExpired = PengaturanSiklusTagihan::ambil()->sesuaikanKeHariJatuhTempo($tanggalBaru);
        } else {
            $baseDate = ($currentExpired && $currentExpired->isFuture())
                ? $currentExpired->copy()
                : $dibayarPada->copy()->startOfDay();

            $newExpired = $baseDate->addDays($masaNilai);
        }

        $layanan->update([
            'tanggal_expired' => $newExpired->toDateString(),
            'status' => StatusLayanan::Aktif,
        ]);

        return $layanan;
    }
}
