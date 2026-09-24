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
        $layanan->update([
            'tanggal_expired' => $this->hitungExpiredBaru($layanan, $invoice, $dibayarPada)->toDateString(),
            'status' => StatusLayanan::Aktif,
        ]);

        return $layanan;
    }

    /**
     * Tanggal expired layanan setelah $invoice dibayar pada $dibayarPada, tanpa menulis apa pun.
     * Dipakai execute() dan placeholder `{hingga}` Template Deskripsi Tagihan Gateway agar
     * keduanya tidak pernah berbeda hasil.
     */
    public function hitungExpiredBaru(LayananPelanggan $layanan, Invoice $invoice, Carbon $dibayarPada): Carbon
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

            return PengaturanSiklusTagihan::ambil()->sesuaikanKeHariJatuhTempo($tanggalBaru);
        }

        $baseDate = ($currentExpired && $currentExpired->isFuture())
            ? $currentExpired->copy()
            : $dibayarPada->copy()->startOfDay();

        return $baseDate->addDays($masaNilai);
    }

    /**
     * Kebalikan execute() untuk pembatalan invoice lunas: kurangi masa aktif sebanyak yang
     * ditambahkan pelunasan itu (siklus digabung + bonus promo), lalu sesuaikan ke Hari Jatuh
     * Tempo. Tidak mengubah status layanan -- pemanggil yang memutuskan isolir.
     *
     * Paket harian: dikurangi `masa_aktif_nilai` hari. Bila dulu basisnya tanggal bayar
     * (expired lama sudah lewat), hasilnya hanya mendekati expired lama.
     */
    public function batalkan(LayananPelanggan $layanan, Invoice $invoice): LayananPelanggan
    {
        $expired = $layanan->tanggal_expired ? Carbon::parse($layanan->tanggal_expired) : null;

        if (! $expired) {
            return $layanan;
        }

        $paket = $layanan->paketLayanan;
        $masaNilai = $paket ? (int) $paket->masa_aktif_nilai : 1;
        $masaSatuan = $paket ? $paket->masa_aktif_satuan : MasaAktifSatuan::Bulan;

        if ($masaSatuan === MasaAktifSatuan::Bulan) {
            $bonusBulan = (int) ($invoice->promo?->bonus_bulan ?? 0);
            $siklus = 1 + $invoice->invoiceDigabung()->count();
            $baru = PengaturanSiklusTagihan::ambil()
                ->sesuaikanKeHariJatuhTempo($expired->copy()->subMonthsNoOverflow(($masaNilai * $siklus) + $bonusBulan));
        } else {
            $baru = $expired->copy()->subDays($masaNilai);
        }

        $layanan->update(['tanggal_expired' => $baru->toDateString()]);

        return $layanan;
    }
}
