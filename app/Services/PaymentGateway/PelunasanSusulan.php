<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\DTO\PaymentGateway\PaymentCallbackData;
use App\Enums\AksiPelunasanSusulan;
use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Exceptions\PembayaranSandboxDiabaikan;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use App\Notifications\PelunasanSusulanNotification;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Pelunasan Susulan (CONTEXT.md, ADR-0069): pembayaran yang sudah PAID di Xendit tetapi belum
 * Lunas di sistem. Satu-satunya tempat yang mencocokkan pembayaran gateway ke invoice dan
 * memutuskan apakah dilunasi atau dilaporkan untuk tindakan manual.
 */
class PelunasanSusulan
{
    public function __construct(private PaymentGatewayManager $manager) {}

    /**
     * @return list<HasilPelunasanSusulan>
     */
    public function jalankan(CarbonInterface $sejak, bool $dryRun = false): array
    {
        $driver = $this->manager->driver('xendit');
        $hasil = [];

        foreach ($this->koneksiXendit() as $koneksi) {
            try {
                /** @var XenditDriver $driver */
                $daftar = $driver->daftarPembayaranLunas($koneksi, $sejak);
            } catch (Throwable $e) {
                Log::error("Pelunasan Susulan: gagal mengambil pembayaran Xendit koneksi {$koneksi->nama}: ".$e->getMessage());
                $hasil[] = new HasilPelunasanSusulan(AksiPelunasanSusulan::GagalKoneksi, $koneksi->nama, keterangan: $e->getMessage());

                continue;
            }

            foreach ($daftar as $pembayaran) {
                $hasil[] = $this->tangani($pembayaran, $koneksi->nama, $dryRun);
            }
        }

        return $hasil;
    }

    /**
     * Catat setiap pelunasan dan kasus yang perlu ditindaklanjuti ke audit trail, lalu kabari
     * Admin dan super_admin sekali per eksekusi -- hanya bila ada isinya.
     *
     * @param  list<HasilPelunasanSusulan>  $hasil
     */
    public function laporkan(array $hasil): void
    {
        $dilaporkan = array_values(array_filter($hasil, fn (HasilPelunasanSusulan $item): bool => $item->aksi->perluDilaporkan()));

        foreach ($dilaporkan as $item) {
            $log = activity('pelunasan_susulan')->withProperties([
                'aksi' => $item->aksi->value,
                'koneksi' => $item->koneksi,
                'external_id' => $item->pembayaran?->externalId,
                'xendit_id' => $item->pembayaran?->eventId,
                'nominal' => $item->pembayaran?->paidAmount,
                'dibayar_pada' => $item->pembayaran?->paidAt,
                'status_sebelum' => $item->statusSebelum,
                'keterangan' => $item->keterangan,
            ]);

            if ($item->invoice) {
                $log->performedOn($item->invoice);
            }

            $log->log("Pelunasan Susulan {$item->aksi->value}: ".($item->invoice?->no_invoice ?? $item->pembayaran?->externalId ?? $item->koneksi));
        }

        if ($dilaporkan === []) {
            return;
        }

        $dilunasi = count(array_filter($dilaporkan, fn (HasilPelunasanSusulan $item): bool => $item->aksi === AksiPelunasanSusulan::Dilunasi));

        Notification::send(
            User::role(['super_admin', 'admin'])->get(),
            new PelunasanSusulanNotification($dilunasi, count($dilaporkan) - $dilunasi),
        );
    }

    /**
     * Semua Koneksi Xendit non-sandbox yang punya kredensial, termasuk yang sudah nonaktif:
     * pelanggan bisa membayar link yang diterbitkan akun lama.
     *
     * @return Collection<int, PengaturanGateway>
     */
    protected function koneksiXendit(): Collection
    {
        return PengaturanGateway::query()
            ->where(fn ($query) => $query->where('provider', 'xendit')->orWhere('gateway', 'xendit'))
            ->where('sandbox_mode', false)
            ->orderBy('id')
            ->get()
            ->filter(fn (PengaturanGateway $koneksi): bool => filled($koneksi->credentials['secret_key'] ?? null))
            ->values();
    }

    public function tangani(PaymentCallbackData $pembayaran, string $koneksi, bool $dryRun = false): HasilPelunasanSusulan
    {
        [$invoice, $transaksi] = $this->cariTarget($pembayaran);
        $hasil = fn (AksiPelunasanSusulan $aksi, string $keterangan = '') => new HasilPelunasanSusulan(
            $aksi, $koneksi, $pembayaran, $invoice, $invoice?->status->label(), $keterangan,
        );

        if (! $invoice) {
            return $hasil(AksiPelunasanSusulan::Dilaporkan, 'external_id tidak dikenal di sistem.');
        }

        if ($this->sudahTercatat($pembayaran, $invoice, $transaksi)) {
            return $hasil(AksiPelunasanSusulan::SudahTercatat);
        }

        if ($invoice->isLunas()) {
            return $hasil(AksiPelunasanSusulan::Dilaporkan, 'Pembayaran ganda: invoice sudah Lunas lewat pembayaran lain, refund di Xendit.');
        }

        $penggabung = null;
        if ($invoice->isDigabung()) {
            $penggabung = Invoice::query()->find($invoice->digabung_ke_invoice_id);

            if (! $penggabung || $penggabung->isLunas()) {
                return $hasil(AksiPelunasanSusulan::Dilaporkan, "Pembayaran ganda: tunggakan ini sudah dilunasi lewat invoice penggabung {$penggabung?->no_invoice}, refund di Xendit.");
            }
        } elseif ($invoice->status === StatusInvoice::Dibatalkan) {
            if ($alasan = $this->alasanTidakDipulihkan($invoice)) {
                return $hasil(AksiPelunasanSusulan::Dilaporkan, $alasan);
            }
        } elseif (! in_array($invoice->status, [StatusInvoice::MenungguPembayaran, StatusInvoice::Kadaluarsa], true)) {
            return $hasil(AksiPelunasanSusulan::Dilaporkan, "Invoice berstatus {$invoice->status->label()}: perlu tindakan manual.");
        }

        if ($alasan = $this->alasanDitolak($pembayaran, $invoice, $transaksi)) {
            return $hasil(AksiPelunasanSusulan::Dilaporkan, $alasan);
        }

        $catatanPenggabung = match (true) {
            $penggabung !== null => "Dilepas dari invoice penggabung {$penggabung->no_invoice}, nominalnya dikoreksi.",
            $invoice->status === StatusInvoice::Dibatalkan => 'Invoice yang dibatalkan dipulihkan.',
            default => '',
        };

        if ($dryRun) {
            return $hasil(AksiPelunasanSusulan::AkanDilunasi, $catatanPenggabung);
        }

        try {
            DB::transaction(function () use ($invoice, $penggabung, $pembayaran, $transaksi): void {
                if ($penggabung) {
                    $this->lepaskanDariPenggabung($invoice, $penggabung);
                } elseif ($invoice->status === StatusInvoice::Dibatalkan) {
                    $invoice->restore();
                    $invoice->update(['status' => StatusInvoice::MenungguPembayaran]);
                }

                if (! $this->manager->prosesPelunasan($invoice, $pembayaran, $transaksi)) {
                    throw new PembayaranSandboxDiabaikan;
                }
            });
        } catch (PembayaranSandboxDiabaikan) {
            return $hasil(AksiPelunasanSusulan::Dilaporkan, 'Diabaikan: transaksi berasal dari koneksi sandbox.');
        }

        $gagalLink = $penggabung ? $this->terbitkanUlangLinkPenggabung($penggabung) : null;

        return $hasil(AksiPelunasanSusulan::Dilunasi, trim($catatanPenggabung.' '.$gagalLink));
    }

    /**
     * Invoice Dibatalkan hanya dipulihkan bila tidak membuat masa aktif bertambah dua kali dan
     * tidak dulunya menyerap tunggakan (rantai penggabungan tidak dibangun ulang otomatis).
     */
    protected function alasanTidakDipulihkan(Invoice $invoice): ?string
    {
        if ((float) $invoice->jumlah_tunggakan > 0) {
            return 'Invoice yang dibatalkan dulunya menggabung tunggakan: perlu tindakan manual.';
        }

        $periodeSudahLunas = $invoice->periode_tagihan !== null && Invoice::query()
            ->where('layanan_pelanggan_id', $invoice->layanan_pelanggan_id)
            ->where('periode_tagihan', $invoice->periode_tagihan)
            ->where('status', StatusInvoice::Lunas)
            ->whereKeyNot($invoice->id)
            ->exists();

        return $periodeSudahLunas
            ? "Pembayaran ganda: periode {$invoice->periode_tagihan} sudah Lunas lewat invoice lain, refund di Xendit."
            : null;
    }

    /**
     * Tunggakan yang dibayar terpisah keluar dari invoice penggabungnya: nominal penggabung
     * berkurang sebesar tunggakan itu agar pelanggan tidak tertagih dua kali (ADR-0069).
     */
    protected function lepaskanDariPenggabung(Invoice $invoice, Invoice $penggabung): void
    {
        $penggabung = Invoice::query()->whereKey($penggabung->id)->lockForUpdate()->firstOrFail();
        $nominal = (float) $invoice->jumlah_setelah_promo;

        $penggabung->update([
            'jumlah_setelah_promo' => max(0.0, (float) $penggabung->jumlah_setelah_promo - $nominal),
            'jumlah_tunggakan' => max(0.0, (float) $penggabung->jumlah_tunggakan - $nominal),
        ]);

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
    protected function terbitkanUlangLinkPenggabung(Invoice $penggabung): ?string
    {
        $transaksiAktif = $penggabung->transaksiPaymentGateways()
            ->where('status', StatusTransaksiGateway::Pending)
            ->latest('id')
            ->first();

        if (! $transaksiAktif) {
            return null;
        }

        try {
            $driver = $this->manager->driver($transaksiAktif->gateway ?: 'xendit');
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

    /**
     * Cocokkan lewat external_id Transaksi Payment Gateway; tanpa transaksi lokal, lewat nomor
     * invoice di depan external_id (`{no_invoice}-{timestamp}`) atau id invoice Xendit.
     *
     * @return array{0: Invoice|null, 1: TransaksiPaymentGateway|null}
     */
    public function cariTarget(PaymentCallbackData $pembayaran): array
    {
        $transaksi = TransaksiPaymentGateway::query()->where('external_id', $pembayaran->externalId)->first();

        if ($transaksi) {
            return [Invoice::withTrashed()->find($transaksi->invoice_id), $transaksi];
        }

        $noInvoice = preg_replace('/-\d{9,}$/', '', $pembayaran->externalId);
        $invoice = Invoice::withTrashed()->where('no_invoice', $noInvoice)->first()
            ?? ($pembayaran->eventId
                ? Invoice::withTrashed()->where('payment_gateway_id', $pembayaran->eventId)->orWhere('xendit_invoice_id', $pembayaran->eventId)->first()
                : null);

        return [$invoice, null];
    }

    /**
     * Pembayaran ini sendiri sudah pernah dicatat (webhook atau sinkron sebelumnya).
     */
    protected function sudahTercatat(PaymentCallbackData $pembayaran, Invoice $invoice, ?TransaksiPaymentGateway $transaksi): bool
    {
        if ($invoice->isLunas() && $transaksi?->status === StatusTransaksiGateway::Paid) {
            return true;
        }

        $referensi = array_values(array_filter([$pembayaran->paymentReference, $pembayaran->eventId, $pembayaran->externalId]));

        return Pembayaran::query()->where('invoice_id', $invoice->id)->whereIn('referensi_transaksi', $referensi)->exists();
    }

    /**
     * Validasi yang sama dengan webhook: hanya IDR, dan nominal sama persis (Validasi Ketat Nominal Gateway).
     */
    protected function alasanDitolak(PaymentCallbackData $pembayaran, Invoice $invoice, ?TransaksiPaymentGateway $transaksi): ?string
    {
        if (filled($pembayaran->currency) && strtoupper((string) $pembayaran->currency) !== 'IDR') {
            return "Mata uang {$pembayaran->currency}, seharusnya IDR.";
        }

        $seharusnya = (int) round($transaksi ? (float) $transaksi->total_tagihan : (float) $invoice->jumlah_setelah_promo);
        $diterima = (int) round($pembayaran->paidAmount);

        return $diterima === $seharusnya
            ? null
            : 'Nominal tidak sama: diterima Rp '.number_format($diterima, 0, ',', '.').', seharusnya Rp '.number_format($seharusnya, 0, ',', '.').'.';
    }
}
