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
     * Masa aktif mengikuti rentang layanan yang tersimpan pada invoice. Invoice gabungan memakai
     * rentang invoice periodik yang diserap, sehingga nominal dan tanggal pembayaran tidak
     * menentukan jumlah bulan secara heuristik.
     *
     * Invoice lama tanpa rentang eksplisit tetap memakai aturan paket sebagai fallback.
     *
     * Pemanggil bertanggung jawab mengunci baris $layanan (lockForUpdate) dan membungkus
     * pemanggilan ini dalam transaksi database miliknya sendiri -- action ini tidak membuka
     * transaksi baru agar tetap atomic bersama mutasi invoice/pembayaran di pemanggil.
     */
    public function execute(LayananPelanggan $layanan, Invoice $invoice, Carbon $dibayarPada): LayananPelanggan
    {
        $invoice->update([
            'masa_aktif_sebelum' => $layanan->tanggal_expired?->toDateString(),
        ]);
        $hingga = $this->hitungExpiredBaru($layanan, $invoice, $dibayarPada)->toDateString();

        $attributes = ['tanggal_expired' => $hingga];
        if (Carbon::parse($hingga)->isToday() || Carbon::parse($hingga)->isFuture()) {
            if ($layanan->status !== StatusLayanan::Proses) {
                $attributes['status'] = StatusLayanan::Aktif;
            }
        }

        $layanan->update($attributes);
        $invoice->update(['masa_aktif_hingga' => $hingga]);

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
        $currentExpired = $layanan->tanggal_expired ? Carbon::parse($layanan->tanggal_expired) : null;

        if ($invoice->masa_aktif_selesai) {
            return $this->hitungDariPeriodeInvoice($invoice, $currentExpired);
        }

        // Fallback untuk invoice lama yang belum memiliki rentang periode tersimpan.
        return $this->hitungDariPaket($invoice, $dibayarPada, $currentExpired, $masaNilai, $masaSatuan);
    }

    private function hitungDariPeriodeInvoice(
        Invoice $invoice,
        ?Carbon $currentExpired,
    ): Carbon {
        $tanggalPeriode = Carbon::parse($invoice->masa_aktif_selesai);

        foreach ($invoice->invoiceDigabung()->get() as $invoiceDigabung) {
            if ($invoiceDigabung->masa_aktif_selesai) {
                $tanggalPeriode = $tanggalPeriode->max(Carbon::parse($invoiceDigabung->masa_aktif_selesai));
            }
        }

        $bonusBulan = (int) ($invoice->promo?->bonus_bulan ?? 0);
        if ($bonusBulan > 0) {
            $tanggalPeriode = $tanggalPeriode->addMonthsNoOverflow($bonusBulan);
        }

        if ($invoice->periode_tagihan !== null
            && $currentExpired
            && $currentExpired->isFuture()
            && $currentExpired->greaterThan($tanggalPeriode)) {
            return $currentExpired;
        }

        return $tanggalPeriode;
    }

    private function hitungDariPaket(
        Invoice $invoice,
        Carbon $dibayarPada,
        ?Carbon $currentExpired,
        int $masaNilai,
        MasaAktifSatuan $masaSatuan,
    ): Carbon {
        $promo = $invoice->promo;
        $bonusBulan = ($promo && $promo->bonus_bulan) ? (int) $promo->bonus_bulan : 0;

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

        if ($invoice->masa_aktif_sebelum) {
            $layanan->update(['tanggal_expired' => $invoice->masa_aktif_sebelum->toDateString()]);

            return $layanan;
        }

        $paket = $layanan->paketLayanan;
        $masaNilai = $paket ? (int) $paket->masa_aktif_nilai : 1;
        $masaSatuan = $paket ? $paket->masa_aktif_satuan : MasaAktifSatuan::Bulan;

        if ($masaSatuan === MasaAktifSatuan::Bulan) {
            $bonusBulan = (int) ($invoice->promo->bonus_bulan ?? 0);
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
