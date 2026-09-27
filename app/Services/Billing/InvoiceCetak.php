<?php

namespace App\Services\Billing;

use App\Actions\LayananPelanggan\PerpanjangMasaAktifAction;
use App\Enums\GatewayChannel;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Models\Invoice;
use App\Models\Pembayaran;
use Illuminate\Support\Carbon;

/**
 * Isi Invoice PDF untuk pelanggan: baris per komponen tagihan (paket, add-on IP Publik,
 * tunggakan) agar subtotal selalu cocok dengan total yang ditagih, tanpa data sensitif
 * (PPP, router, alamat IP).
 */
class InvoiceCetak
{
    /**
     * @param  list<array{deskripsi: string, harga: float, qty: int}>  $baris
     */
    private function __construct(
        public readonly string $keterangan,
        public readonly array $baris,
        public readonly float $subtotal,
        public readonly float $diskon,
        public readonly float $total,
        public readonly ?Pembayaran $pembayaran,
        public readonly ?string $metode,
        public readonly ?string $teller,
    ) {}

    public static function dari(Invoice $invoice): self
    {
        $invoice->loadMissing(['layananPelanggan.paketLayanan', 'layananPelanggan.ipPubliks', 'promo', 'pembayarans.dicatatOleh', 'transaksiPaymentGateways']);
        $layanan = $invoice->layananPelanggan;
        $keterangan = self::keterangan($invoice);

        $baris = [];
        $jumlah = (float) $invoice->jumlah;

        $addOns = [];
        if ($invoice->periode_tagihan && $layanan) {
            $addOns = $layanan->ipPubliks->map(fn ($ip): float => (float) $ip->harga_ditagih)->all();
        }

        if ($addOns && array_sum($addOns) < $jumlah) {
            // ponytail: harga add-on dibaca dari layanan saat cetak (tidak di-snapshot per invoice);
            // bila add-on berubah setelah invoice terbit, pembagian paket/add-on bergeser tapi subtotal tetap benar.
            $baris[] = ['deskripsi' => $keterangan, 'harga' => $jumlah - array_sum($addOns), 'qty' => 1];
            foreach ($addOns as $harga) {
                $baris[] = ['deskripsi' => 'Add-on IP Publik Dedicated', 'harga' => $harga, 'qty' => 1];
            }
        } else {
            $baris[] = ['deskripsi' => $keterangan, 'harga' => $jumlah, 'qty' => 1];
        }

        $tunggakan = (float) $invoice->jumlah_tunggakan;
        if ($tunggakan > 0) {
            $baris[] = ['deskripsi' => 'Tunggakan periode sebelumnya', 'harga' => $tunggakan, 'qty' => 1];
        }

        $subtotal = $jumlah + $tunggakan;
        $total = (float) $invoice->jumlah_setelah_promo;

        $pembayaran = $invoice->status === StatusInvoice::Lunas
            ? $invoice->pembayarans->sortByDesc('dibayar_pada')->first()
            : null;

        return new self(
            keterangan: $keterangan,
            baris: $baris,
            subtotal: $subtotal,
            diskon: max(0.0, $subtotal - $total),
            total: $total,
            pembayaran: $pembayaran,
            metode: $pembayaran ? self::metode($invoice, $pembayaran) : null,
            teller: $pembayaran ? ($pembayaran->dicatatOleh->name ?? ($pembayaran->metode === MetodePembayaran::PaymentGateway ? 'Otomatis (Payment Gateway)' : '-')) : null,
        );
    }

    /**
     * `({site_id}) Pembayaran Internet Periode {bulan} {paket} hingga {Y-m-d}`; invoice tanpa
     * periode memakai `({site_id}) {keterangan}`.
     */
    private static function keterangan(Invoice $invoice): string
    {
        $layanan = $invoice->layananPelanggan;
        $site = '('.($layanan->site_id ?? '-').')';

        if (! $invoice->periode_tagihan) {
            return $site.' '.($invoice->keterangan ?: 'Tagihan Internet');
        }

        $bulan = (Carbon::createFromFormat('!Y-m', $invoice->periode_tagihan) ?: Carbon::parse($invoice->tanggal_terbit))->translatedFormat('F Y');
        $teks = "{$site} Pembayaran Internet Periode {$bulan} ".($layanan?->paketLayanan->nama_paket ?? 'Layanan Internet');

        // Lunas: snapshot saat dibayar. Belum dibayar: proyeksi. Lunas lama tanpa snapshot: tanpa "hingga".
        $hingga = match (true) {
            $invoice->masa_aktif_hingga !== null => $invoice->masa_aktif_hingga,
            in_array($invoice->status, StatusInvoice::terbuka(), true) && $layanan !== null => app(PerpanjangMasaAktifAction::class)->hitungExpiredBaru($layanan, $invoice, Carbon::now()),
            default => null,
        };

        return $hingga ? "{$teks} hingga {$hingga->toDateString()}" : $teks;
    }

    private static function metode(Invoice $invoice, Pembayaran $pembayaran): string
    {
        if ($pembayaran->metode !== MetodePembayaran::PaymentGateway) {
            return $pembayaran->metode->label();
        }

        $transaksi = $invoice->transaksiPaymentGateways
            ->where('status', StatusTransaksiGateway::Paid)
            ->sortByDesc('id')
            ->first();

        if (! $transaksi || $transaksi->channel === GatewayChannel::Invoice) {
            return $pembayaran->metode->label();
        }

        return trim($transaksi->channel->label().' '.strtoupper((string) $transaksi->channel_detail));
    }
}
