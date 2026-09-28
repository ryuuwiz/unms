<?php

namespace App\Actions\Invoice;

use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class HapusPermanenInvoiceAction
{
    /**
     * Hapus sungguhan (forceDelete) invoice -- lihat CONTEXT.md "Penghapusan Permanen Invoice" & ADR-0064.
     *
     * Hanya untuk invoice berstatus Dibatalkan yang tidak pernah punya riwayat Pembayaran
     * (termasuk yang sudah soft-delete) atau Transaksi Payment Gateway sama sekali.
     *
     * @throws Exception
     */
    public function execute(Invoice $invoice, User $actor, string $alasan): void
    {
        DB::transaction(function () use ($invoice, $actor, $alasan) {
            /** @var Invoice $locked */
            $locked = Invoice::withTrashed()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StatusInvoice::Dibatalkan) {
                throw new Exception("Invoice {$locked->no_invoice} harus berstatus Dibatalkan sebelum dihapus permanen.");
            }

            if (Pembayaran::withTrashed()->where('invoice_id', $locked->id)->exists()) {
                throw new Exception("Invoice {$locked->no_invoice} pernah punya riwayat pembayaran dan tidak dapat dihapus permanen.");
            }

            if (TransaksiPaymentGateway::where('invoice_id', $locked->id)->exists()) {
                throw new Exception("Invoice {$locked->no_invoice} pernah punya riwayat transaksi payment gateway dan tidak dapat dihapus permanen.");
            }

            activity('invoice')
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties(['action' => 'hapus_permanen_invoice', 'alasan' => $alasan])
                ->log("Menghapus permanen invoice {$locked->no_invoice}");

            $locked->forceDelete();
        });
    }
}
