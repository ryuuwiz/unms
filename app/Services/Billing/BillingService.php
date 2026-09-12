<?php

namespace App\Services\Billing;

use App\Actions\LayananPelanggan\PerpanjangMasaAktifAction;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Events\InvoicePaidEvent;
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
        $eventToDispatch = null;

        $pembayaran = DB::transaction(function () use ($invoice, $payload, $actor, &$eventToDispatch) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            // Guard clause idempotensi: Jika sudah lunas, jangan proses ulang
            if ($lockedInvoice->status === StatusInvoice::Lunas) {
                throw new Exception("Invoice {$lockedInvoice->no_invoice} sudah berstatus lunas.");
            }

            // Validasi Ketat Nominal (konsisten dengan jalur payment gateway di
            // ProcessPaymentWebhookJob): pembayaran manual wajib melunasi persis sejumlah
            // tagihan, tidak kurang maupun lebih. Model belum mendukung cicilan/partial
            // payment, jadi nominal parsial atau berlebih harus ditolak tegas di sini,
            // bukan diam-diam menandai invoice lunas.
            $jumlahDibayar = (int) round((float) $payload['jumlah_dibayar']);
            $jumlahTagihan = (int) round((float) $lockedInvoice->jumlah_setelah_promo);

            if ($jumlahDibayar !== $jumlahTagihan) {
                throw new Exception(
                    "Nominal pembayaran Rp ".number_format($jumlahDibayar, 0, ',', '.').
                    ' tidak sama dengan tagihan Rp '.number_format($jumlahTagihan, 0, ',', '.').
                    " pada Invoice {$lockedInvoice->no_invoice}. Pembayaran manual wajib melunasi penuh."
                );
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

            // 3. Perpanjang masa aktif layanan pelanggan (PRD 4.2), aturan tunggal isFuture()
            // yang sama dengan jalur payment gateway -- lihat PerpanjangMasaAktifAction.
            $layanan = $lockedInvoice->layananPelanggan()->lockForUpdate()->first();
            if ($layanan) {
                app(PerpanjangMasaAktifAction::class)->execute($layanan, $lockedInvoice, $dibayarPada);
            }

            // Siapkan event untuk dipancarkan setelah commit DB (lihat pola serupa di
            // PaymentGatewayManager::processWebhook()), agar notifikasi WA pembayaran juga
            // terkirim untuk pembayaran manual/kasir, bukan hanya via payment gateway online.
            $eventToDispatch = new InvoicePaidEvent($lockedInvoice, $pembayaran);

            return $pembayaran;
        });

        if ($eventToDispatch) {
            event($eventToDispatch);
        }

        return $pembayaran;
    }
}
