<?php

namespace App\Livewire\Pembayaran\TransaksiGateway;

use App\Models\Invoice;
use App\Models\Pembayaran;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\CekStatusPembayaranInvoice;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Transaksi Payment Gateway')]
class Show extends Component
{
    public TransaksiPaymentGateway $transaksi;

    /** @var array{lunas: bool, alasanManual: string|null, galat: string|null}|null */
    public ?array $hasilCekStatus = null;

    public function mount(TransaksiPaymentGateway $transaksi): void
    {
        $this->authorize('viewAny', Pembayaran::class);
        $this->transaksi = $transaksi->load(['invoice.pelanggan', 'webhookLogs' => fn ($q) => $q->latest('id')]);
    }

    /**
     * Cek semua transaksi invoice ke gateway (termasuk link lama); PAID melunasi invoice lewat sinkron biasa.
     */
    public function cekStatusPembayaran(CekStatusPembayaranInvoice $cekStatus): void
    {
        $this->authorize('viewAny', Pembayaran::class);

        $invoice = Invoice::withTrashed()->find($this->transaksi->invoice_id);
        if (! $invoice) {
            Flux::toast(variant: 'warning', text: 'Invoice transaksi ini tidak ditemukan.');

            return;
        }

        try {
            $this->hasilCekStatus = $cekStatus->periksa($invoice)->toArray();
            $this->transaksi->refresh();
            $this->transaksi->load(['invoice.pelanggan', 'webhookLogs' => fn ($q) => $q->latest('id')]);
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: 'Gagal rekonsiliasi: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.pembayaran.transaksi-gateway.show');
    }
}
