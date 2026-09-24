<?php

namespace App\Actions\Invoice;

use App\Actions\LayananPelanggan\PerpanjangMasaAktifAction;
use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BatalkanInvoiceLunasAction
{
    public function __construct(
        private PerpanjangMasaAktifAction $perpanjangMasaAktif,
        private UbahStatusLayananAction $ubahStatusLayanan,
    ) {}

    /**
     * Void invoice Lunas beserta rollback-nya -- lihat CONTEXT.md "Pembatalan Invoice Lunas".
     *
     * Hanya untuk pelunasan manual/transfer dan hanya pembayaran terakhir sebuah layanan
     * (perpanjangan yang lebih baru bertumpuk di atasnya). Pembayaran di-soft-delete (keluar
     * dari laporan), masa aktif layanan dikembalikan, invoice yang tadinya digabung dilepas
     * jadi Kadaluarsa, dan layanan yang jadi lewat masa aktif diisolir.
     *
     * @throws Exception
     */
    public function execute(Invoice $invoice, User $actor, string $alasan): Invoice
    {
        $suspendLayanan = null;

        DB::transaction(function () use ($invoice, $actor, $alasan, &$suspendLayanan) {
            /** @var Invoice $locked */
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StatusInvoice::Lunas) {
                throw new Exception("Invoice {$locked->no_invoice} tidak berstatus lunas.");
            }

            if ($locked->metode_pembayaran === MetodePembayaran::PaymentGateway) {
                throw new Exception('Invoice yang dilunasi lewat payment gateway tidak dapat dibatalkan dari sini; lakukan refund di gateway dan hubungi super admin.');
            }

            $pembayaranTerakhir = Pembayaran::query()
                ->whereHas('invoice', fn ($q) => $q->where('layanan_pelanggan_id', $locked->layanan_pelanggan_id))
                ->orderByDesc('dibayar_pada')
                ->orderByDesc('id')
                ->first();

            if ($pembayaranTerakhir && $pembayaranTerakhir->invoice_id !== $locked->id) {
                throw new Exception('Hanya pembayaran terakhir layanan ini yang dapat dibatalkan; batalkan pembayaran yang lebih baru terlebih dahulu.');
            }

            $layanan = $locked->layananPelanggan()->lockForUpdate()->first();
            $expiredLama = $layanan?->tanggal_expired?->toDateString();

            if ($layanan) {
                $this->perpanjangMasaAktif->batalkan($layanan, $locked);

                if ($layanan->status === StatusLayanan::Aktif && $layanan->tanggal_expired?->lt(Carbon::today())) {
                    $suspendLayanan = $layanan;
                }
            }

            $locked->invoiceDigabung->each->update([
                'status' => StatusInvoice::Kadaluarsa,
                'digabung_ke_invoice_id' => null,
            ]);

            $pembayaranIds = $locked->pembayarans->each->delete()->pluck('id')->all();

            $locked->update([
                'status' => StatusInvoice::Dibatalkan,
                'dihapus_oleh' => $actor->id,
                'keterangan_hapus' => $alasan,
            ]);
            $locked->delete();

            activity('invoice')
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties([
                    'action' => 'batalkan_invoice_lunas',
                    'alasan' => $alasan,
                    'pembayaran_ids' => $pembayaranIds,
                    'tanggal_expired_sebelum' => $expiredLama,
                    'tanggal_expired_sesudah' => $layanan?->fresh()?->tanggal_expired?->toDateString(),
                ])
                ->log("Membatalkan invoice lunas {$locked->no_invoice}");
        });

        if ($suspendLayanan) {
            $this->ubahStatusLayanan->execute($suspendLayanan, StatusLayanan::Suspend, $actor, "Invoice lunas {$invoice->no_invoice} dibatalkan; masa aktif kembali lewat.");
        }

        return $invoice->refresh();
    }
}
