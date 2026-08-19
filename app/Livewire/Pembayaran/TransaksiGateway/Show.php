<?php

namespace App\Livewire\Pembayaran\TransaksiGateway;

use App\Models\Pembayaran;
use App\Models\TransaksiPaymentGateway;
use App\Services\Xendit\XenditPaymentService;
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

    public ?array $reconciliationResult = null;

    public function mount(TransaksiPaymentGateway $transaksi): void
    {
        $this->authorize('viewAny', Pembayaran::class);
        $this->transaksi = $transaksi->load(['invoice.pelanggan', 'webhookLogs' => fn ($q) => $q->latest('id')]);
    }

    public function rekonsiliasiStatus(XenditPaymentService $paymentService): void
    {
        $this->reconciliationResult = $paymentService->cekStatusTransaksi($this->transaksi);
        Flux::toast(variant: 'info', text: 'Status berhasil diperiksa dari Xendit API.');
    }

    public function render(): View
    {
        return view('livewire.pembayaran.transaksi-gateway.show');
    }
}
