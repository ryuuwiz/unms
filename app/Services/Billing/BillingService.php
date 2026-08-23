<?php

namespace App\Services\Billing;

use App\Enums\MasaAktifSatuan;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pembayaran;
use App\Models\Promo;
use App\Models\PromoPenggunaan;
use App\Models\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * Terbitkan invoice baru untuk sebuah layanan pelanggan.
     */
    public function generateInvoice(
        LayananPelanggan $layanan,
        ?int $dibuatOleh = null,
        ?Promo $promo = null,
        ?Carbon $tanggalJatuhTempo = null,
        ?string $periodeTagihan = null
    ): Invoice {
        return DB::transaction(function () use ($layanan, $dibuatOleh, $promo, $tanggalJatuhTempo, $periodeTagihan) {
            $periode = $periodeTagihan ?? $layanan->getNextPeriodeTagihan();

            // Guard clause idempotensi: jika invoice non-batal sudah ada untuk layanan & periode ini, return existing
            /** @var Invoice|null $existingInvoice */
            $existingInvoice = Invoice::where('layanan_pelanggan_id', $layanan->id)
                ->where('periode_tagihan', $periode)
                ->where('status', '!=', StatusInvoice::Dibatalkan)
                ->lockForUpdate()
                ->first();

            if ($existingInvoice) {
                return $existingInvoice;
            }

            $paket = $layanan->paketLayanan;
            $harga = (float) $paket->harga;

            $diskon = 0.0;
            if ($promo && $promo->aktif) {
                $diskon = $promo->hitungDiskon($harga);
            }

            $jumlahSetelahPromo = max(0.0, $harga - $diskon);

            $terbit = Carbon::today();
            $jatuhTempo = $tanggalJatuhTempo ?? $terbit->copy()->addDays(7);

            $invoice = Invoice::create([
                'periode_tagihan' => $periode,
                'pelanggan_id' => $layanan->pelanggan_id,
                'layanan_pelanggan_id' => $layanan->id,
                'jumlah' => $harga,
                'jumlah_setelah_promo' => $jumlahSetelahPromo,
                'promo_id' => $promo?->id,
                'status' => StatusInvoice::MenungguPembayaran,
                'tanggal_terbit' => $terbit,
                'tanggal_jatuh_tempo' => $jatuhTempo,
                'dibuat_oleh' => $dibuatOleh,
            ]);

            if ($promo && $diskon > 0) {
                PromoPenggunaan::create([
                    'promo_id' => $promo->id,
                    'pelanggan_id' => $layanan->pelanggan_id,
                    'invoice_id' => $invoice->id,
                    'digunakan_pada' => Carbon::now(),
                ]);

                $promo->increment('terpakai_global');
            }

            return $invoice;
        });
    }

    /**
     * Catat dan proses pembayaran manual oleh admin/kasir.
     *
     * @param  array{
     *     metode: string|MetodePembayaran,
     *     jumlah_dibayar: float|numeric,
     *     referensi_transaksi?: string|null,
     *     dibayar_pada?: string|Carbon|null,
     *     bukti_pembayaran_path?: string|null,
     *     catatan?: string|null,
     * }  $payload
     */
    public function prosesPembayaranManual(Invoice $invoice, array $payload, ?User $actor = null): Pembayaran
    {
        return DB::transaction(function () use ($invoice, $payload, $actor) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            // Guard clause idempotensi: Jika sudah lunas, jangan proses ulang
            if ($lockedInvoice->status === StatusInvoice::Lunas) {
                throw new Exception("Invoice {$lockedInvoice->no_invoice} sudah berstatus lunas.");
            }

            $dibayarPada = isset($payload['dibayar_pada'])
                ? Carbon::parse($payload['dibayar_pada'])
                : Carbon::now();

            $metode = $payload['metode'] instanceof MetodePembayaran
                ? $payload['metode']
                : MetodePembayaran::from($payload['metode']);

            // 1. Buat record pembayaran
            $pembayaran = Pembayaran::create([
                'invoice_id' => $lockedInvoice->id,
                'metode' => $metode,
                'referensi_transaksi' => $payload['referensi_transaksi'] ?? null,
                'jumlah_dibayar' => (float) $payload['jumlah_dibayar'],
                'dibayar_pada' => $dibayarPada,
                'bukti_pembayaran_path' => $payload['bukti_pembayaran_path'] ?? null,
                'dicatat_oleh' => $actor?->id,
                'catatan' => $payload['catatan'] ?? null,
            ]);

            // 2. Update status invoice ke lunas
            $lockedInvoice->update([
                'status' => StatusInvoice::Lunas,
                'tanggal_lunas' => $dibayarPada->toDateString(),
                'metode_pembayaran' => $metode,
            ]);

            // 3. Perpanjang masa aktif layanan pelanggan (PRD 4.2)
            $layanan = $lockedInvoice->layananPelanggan()->lockForUpdate()->first();
            if ($layanan) {
                $paket = $layanan->paketLayanan;
                $masaNilai = (int) $paket->masa_aktif_nilai;
                $masaSatuan = $paket->masa_aktif_satuan;

                // Tambahan bonus bulan dari promo bila ada
                $promo = $lockedInvoice->promo;
                $bonusBulan = ($promo && $promo->bonus_bulan) ? (int) $promo->bonus_bulan : 0;

                $currentExpired = $layanan->tanggal_expired ? Carbon::parse($layanan->tanggal_expired) : null;

                // Jika tanggal_expired masih di masa depan -> akumulatif dari expired lama
                // Jika sudah lewat atau belum ada -> hitung dari tanggal bayar
                $baseDate = ($currentExpired && $currentExpired->isFuture())
                    ? $currentExpired->copy()
                    : $dibayarPada->copy()->startOfDay();

                if ($masaSatuan === MasaAktifSatuan::Bulan) {
                    $newExpired = $baseDate->addMonths($masaNilai + $bonusBulan);
                } else {
                    $newExpired = $baseDate->addDays($masaNilai);
                }

                $layanan->update([
                    'tanggal_expired' => $newExpired->toDateString(),
                    'status' => StatusLayanan::Aktif,
                ]);
            }

            return $pembayaran;
        });
    }
}
