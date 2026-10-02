<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\TargetPelunasan;
use App\Enums\AksiPelunasanSusulan;
use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Exceptions\PelunasanSusulanDibatalkan;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Support\Rupiah;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Pelunasan Susulan (CONTEXT.md, ADR-0069): pembayaran yang sudah PAID di Xendit tetapi belum
 * Lunas di sistem. Satu-satunya tempat yang mencocokkan pembayaran gateway ke invoice dan
 * memutuskan apakah dilunasi atau dilaporkan untuk tindakan manual -- dipakai sapuan harian,
 * `pembayaran:pulihkan`, dan webhook untuk invoice Digabung/Dibatalkan.
 */
class PelunasanSusulan
{
    public function __construct(
        private PaymentGatewayManager $manager,
        private KoreksiInvoicePenggabung $koreksiPenggabung,
    ) {}

    /**
     * @return list<HasilPelunasanSusulan>
     */
    public function jalankan(CarbonInterface $sejak, bool $dryRun = false): array
    {
        $driver = $this->manager->driver(XenditDriver::PROVIDER);
        if (! $driver instanceof XenditDriver) {
            throw new LogicException('Pelunasan Susulan membutuhkan driver Xendit.');
        }

        $hasil = [];
        foreach ($this->koneksiXendit() as $koneksi) {
            try {
                $daftar = $driver->daftarPembayaranLunas($koneksi, $sejak);
            } catch (Throwable $e) {
                Log::error("Pelunasan Susulan: gagal mengambil pembayaran Xendit koneksi {$koneksi->nama}: ".$e->getMessage());
                $hasil[] = new HasilPelunasanSusulan(AksiPelunasanSusulan::GagalKoneksi, $koneksi->nama, keterangan: $e->getMessage());

                continue;
            }

            foreach ($daftar as $pembayaran) {
                $hasil[] = $this->tangani($pembayaran, $koneksi, $dryRun);
            }
        }

        return $hasil;
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
            ->where(fn ($query) => $query->where('provider', XenditDriver::PROVIDER)->orWhere('gateway', XenditDriver::PROVIDER))
            ->where('sandbox_mode', false)
            ->orderBy('id')
            ->get()
            ->filter(fn (PengaturanGateway $koneksi): bool => filled($koneksi->credentials['secret_key'] ?? null))
            ->values();
    }

    /**
     * @param  PengaturanGateway|null  $koneksi  Koneksi asal pembayaran; null bila tidak diketahui (webhook tanpa transaksi).
     */
    public function tangani(PaymentCallbackData $pembayaran, ?PengaturanGateway $koneksi, bool $dryRun = false): HasilPelunasanSusulan
    {
        $target = $this->cariTarget($pembayaran);
        $invoice = $target->invoice;
        $hasil = fn (AksiPelunasanSusulan $aksi, string $keterangan = '', ?string $tindakanManual = null) => new HasilPelunasanSusulan(
            $aksi, $koneksi?->nama ?? 'Webhook', $pembayaran, $invoice, $invoice?->status->label(), $keterangan,
            $aksi === AksiPelunasanSusulan::Dilaporkan ? $keterangan : $tindakanManual,
        );

        if (! $invoice) {
            return $hasil(AksiPelunasanSusulan::Dilaporkan, 'external_id tidak dikenal di sistem.');
        }

        if ($this->sudahTercatat($pembayaran, $target)) {
            return $hasil(AksiPelunasanSusulan::SudahTercatat);
        }

        $rantai = $invoice->isDigabung() ? $invoice->rantaiPenggabung() : collect();

        if ($alasan = $this->alasanDilaporkan($pembayaran, $target, $rantai, $koneksi)) {
            return $hasil(AksiPelunasanSusulan::Dilaporkan, $alasan);
        }

        $catatan = match (true) {
            $rantai->isNotEmpty() => "Dilepas dari invoice penggabung {$rantai->pluck('no_invoice')->implode(' → ')}, nominalnya dikoreksi.",
            $invoice->status === StatusInvoice::Dibatalkan => 'Invoice yang dibatalkan dipulihkan.',
            default => '',
        };

        if ($dryRun) {
            return $hasil(AksiPelunasanSusulan::AkanDilunasi, $catatan);
        }

        try {
            $this->lunasi($pembayaran, $target, $rantai, $koneksi);
        } catch (PelunasanSusulanDibatalkan $e) {
            return $hasil($e->aksi, $e->keterangan);
        }

        $gagalLink = $rantai->isNotEmpty() ? $this->koreksiPenggabung->terbitkanUlangLink($rantai->last()) : null;

        return $hasil(AksiPelunasanSusulan::Dilunasi, trim($catatan.' '.$gagalLink), $gagalLink);
    }

    /**
     * Alasan pembayaran tidak dilunasi otomatis dan dilaporkan untuk tindakan manual (ADR-0069),
     * atau null bila boleh dilunasi. Untuk invoice Digabung yang menentukan adalah ujung rantai
     * penggabung: hanya bila ujungnya masih terbuka tunggakan itu belum dibayar lewat jalur lain.
     *
     * @param  Collection<int, Invoice>  $rantai
     */
    protected function alasanDilaporkan(PaymentCallbackData $pembayaran, TargetPelunasan $target, Collection $rantai, ?PengaturanGateway $koneksi): ?string
    {
        $invoice = $target->invoice;
        $ujung = $rantai->last();

        return match (true) {
            $invoice->isLunas() => 'Pembayaran ganda: invoice sudah Lunas lewat pembayaran lain, refund di Xendit.',
            $invoice->isDigabung() && $ujung?->isLunas() => "Pembayaran ganda: tunggakan ini sudah dilunasi lewat invoice penggabung {$ujung->no_invoice}, refund di Xendit.",
            $invoice->isDigabung() && ! $this->terbuka($ujung) => 'Rantai invoice penggabung tidak berujung pada invoice terbuka: perlu tindakan manual.',
            $invoice->status === StatusInvoice::Dibatalkan => $this->alasanTidakDipulihkan($invoice) ?? $this->alasanDitolak($pembayaran, $target, $koneksi),
            ! $invoice->isDigabung() && ! $this->terbuka($invoice) => "Invoice berstatus {$invoice->status->label()}: perlu tindakan manual.",
            default => $this->alasanDitolak($pembayaran, $target, $koneksi),
        };
    }

    protected function terbuka(?Invoice $invoice): bool
    {
        return in_array($invoice?->status, [StatusInvoice::MenungguPembayaran, StatusInvoice::Kadaluarsa], true);
    }

    /**
     * Invoice Dibatalkan hanya dipulihkan bila tidak membuat masa aktif bertambah dua kali dan
     * tidak dulunya menyerap tunggakan (rantai penggabungan tidak dibangun ulang otomatis).
     */
    protected function alasanTidakDipulihkan(Invoice $invoice): ?string
    {
        return match (true) {
            (float) $invoice->jumlah_tunggakan > 0 => 'Invoice yang dibatalkan dulunya menggabung tunggakan: perlu tindakan manual.',
            $invoice->periodeSudahLunasLewatInvoiceLain() => "Pembayaran ganda: periode {$invoice->periode_tagihan} sudah Lunas lewat invoice lain, refund di Xendit.",
            default => null,
        };
    }

    /**
     * Validasi yang sama dengan webhook: hanya IDR, dan nominal sama persis (Validasi Ketat
     * Nominal Gateway). Tanpa transaksi lokal, nominal tagihan dengan atau tanpa biaya gateway
     * koneksi itu sama-sama diterima karena link bisa diterbitkan dengan biaya dibebankan.
     */
    protected function alasanDitolak(PaymentCallbackData $pembayaran, TargetPelunasan $target, ?PengaturanGateway $koneksi): ?string
    {
        if (filled($pembayaran->currency) && strtoupper((string) $pembayaran->currency) !== 'IDR') {
            return "Mata uang {$pembayaran->currency}, seharusnya IDR.";
        }

        $tagihan = (float) $target->invoice->jumlah_setelah_promo;
        $diterima = (int) round($pembayaran->paidAmount);
        $nominalSah = $target->transaksi
            ? [(int) round((float) $target->transaksi->total_tagihan)]
            : array_unique([(int) round($tagihan), (int) round($tagihan + ($koneksi?->hitungFee('virtual_account', $tagihan) ?? 0.0))]);

        return in_array($diterima, $nominalSah, true)
            ? null
            : 'Nominal tidak sama: diterima '.Rupiah::format($diterima).', seharusnya '.Rupiah::format($nominalSah[0]).'.';
    }

    /**
     * Lepas dari rantai penggabung / pulihkan invoice Dibatalkan lalu lunasi dalam satu transaksi
     * DB. Invoice dikunci dan statusnya dicek ulang agar webhook dan sapuan yang berjalan
     * bersamaan tidak memotong nominal penggabung dua kali.
     *
     * @param  Collection<int, Invoice>  $rantai
     *
     * @throws PelunasanSusulanDibatalkan bila sudah diproses jalur lain atau transaksi sandbox (di-rollback)
     */
    protected function lunasi(PaymentCallbackData $pembayaran, TargetPelunasan $target, Collection $rantai, ?PengaturanGateway $koneksi): void
    {
        DB::transaction(function () use ($pembayaran, $target, $rantai, $koneksi): void {
            $invoice = Invoice::withTrashed()->whereKey($target->invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== $target->invoice->status || $invoice->digabung_ke_invoice_id !== $target->invoice->digabung_ke_invoice_id) {
                throw new PelunasanSusulanDibatalkan(AksiPelunasanSusulan::SudahTercatat);
            }

            if ($rantai->isNotEmpty()) {
                $this->koreksiPenggabung->lepaskanDariRantai($invoice, $rantai);
            } elseif ($invoice->status === StatusInvoice::Dibatalkan) {
                $invoice->restore();
                $invoice->update(['status' => StatusInvoice::MenungguPembayaran]);
            }

            $transaksi = $target->transaksi ?? ($koneksi ? $this->catatTransaksiPembayaran($invoice, $pembayaran, $koneksi) : null);

            if (! $this->manager->prosesPelunasan($invoice, $pembayaran, $transaksi)) {
                throw new PelunasanSusulanDibatalkan(AksiPelunasanSusulan::Dilaporkan, 'Diabaikan: transaksi berasal dari koneksi sandbox.');
            }
        });
    }

    /**
     * Pembayaran tanpa baris transaksi lokal (mis. link lama yang barisnya hilang) dicatat sebagai
     * transaksi koneksi asalnya, sehingga transaksi yang ditandai Paid dan pengecekan sandbox
     * memakai koneksi yang benar, bukan transaksi terakhir atau koneksi default.
     */
    protected function catatTransaksiPembayaran(Invoice $invoice, PaymentCallbackData $pembayaran, PengaturanGateway $koneksi): TransaksiPaymentGateway
    {
        return TransaksiPaymentGateway::create([
            'invoice_id' => $invoice->id,
            'gateway' => XenditDriver::PROVIDER,
            'pengaturan_gateway_id' => $koneksi->id,
            'external_id' => $pembayaran->externalId,
            'xendit_reference_id' => $pembayaran->eventId,
            'channel' => $pembayaran->channel,
            'total_tagihan' => $pembayaran->paidAmount,
            'fee_gateway' => max(0.0, $pembayaran->paidAmount - (float) $invoice->jumlah_setelah_promo),
            'status' => StatusTransaksiGateway::Pending,
        ]);
    }

    /**
     * Cocokkan lewat external_id Transaksi Payment Gateway; tanpa transaksi lokal, lewat nomor
     * invoice di depan external_id (`{no_invoice}-{timestamp}`) atau id invoice Xendit.
     */
    public function cariTarget(PaymentCallbackData $pembayaran): TargetPelunasan
    {
        $transaksi = TransaksiPaymentGateway::query()->where('external_id', $pembayaran->externalId)->first();

        if ($transaksi) {
            return new TargetPelunasan(Invoice::withTrashed()->find($transaksi->invoice_id), $transaksi);
        }

        $noInvoice = preg_replace('/-\d{9,}$/', '', $pembayaran->externalId);
        $invoice = Invoice::withTrashed()->where('no_invoice', $noInvoice)->first()
            ?? ($pembayaran->eventId
                ? Invoice::withTrashed()->where('payment_gateway_id', $pembayaran->eventId)->orWhere('xendit_invoice_id', $pembayaran->eventId)->first()
                : null);

        return new TargetPelunasan($invoice, null);
    }

    /**
     * Pembayaran ini sendiri sudah pernah dicatat (webhook atau sinkron sebelumnya).
     */
    protected function sudahTercatat(PaymentCallbackData $pembayaran, TargetPelunasan $target): bool
    {
        if ($target->invoice->isLunas() && $target->transaksi?->status === StatusTransaksiGateway::Paid) {
            return true;
        }

        $referensi = array_values(array_filter([$pembayaran->paymentReference, $pembayaran->eventId, $pembayaran->externalId]));

        return Pembayaran::query()->where('invoice_id', $target->invoice->id)->whereIn('referensi_transaksi', $referensi)->exists();
    }
}
