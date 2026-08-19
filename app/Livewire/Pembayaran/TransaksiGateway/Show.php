<?php

namespace App\Livewire\Pembayaran\TransaksiGateway;

use App\Enums\StatusTransaksiGateway;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
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

    public function simulasikanPembayaran(XenditPaymentService $paymentService): void
    {
        $this->authorize('viewAny', Pembayaran::class);

        $setting = PengaturanGateway::getXenditSetting();
        if (! $setting->sandbox_mode && ! app()->environment('local', 'testing')) {
            Flux::toast(variant: 'danger', text: 'Simulasi pembayaran hanya diizinkan dalam Sandbox/Development mode.');

            return;
        }

        if ($this->transaksi->status !== StatusTransaksiGateway::Pending) {
            Flux::toast(variant: 'warning', text: 'Hanya transaksi berstatus Pending yang dapat disimulasikan.');

            return;
        }

        $result = $paymentService->simulasikanWebhookLokal($this->transaksi);

        if ($result['success']) {
            $this->transaksi->refresh();
            $this->transaksi->load(['invoice.pelanggan', 'webhookLogs' => fn ($q) => $q->latest('id')]);
            Flux::toast(variant: 'success', text: 'Simulasi pembayaran berhasil! Invoice telah ditandai lunas.');
        } else {
            Flux::toast(variant: 'danger', text: 'Simulasi gagal: '.($result['message'] ?? 'Error tidak diketahui'));
        }
    }

    public function render(): View
    {
        return view('livewire.pembayaran.transaksi-gateway.show');
    }
}
