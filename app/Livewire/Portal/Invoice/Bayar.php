<?php

namespace App\Livewire\Portal\Invoice;

use App\Enums\GatewayChannel;
use App\Enums\StatusTransaksiGateway;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\Xendit\XenditPaymentService;
use Exception;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Pembayaran Tagihan')]
class Bayar extends Component
{
    public Invoice $invoice;

    public string $channelTipe = 'va'; // 'va' atau 'qris'

    public string $bankCode = 'BCA';

    public ?TransaksiPaymentGateway $transaksiAktif = null;

    public bool $loading = false;

    public function mount(Invoice $invoice): void
    {
        $pelangganId = Auth::guard('pelanggan')->user()->pelanggan_id;

        if ($invoice->pelanggan_id !== $pelangganId) {
            abort(403, 'Anda tidak memiliki akses ke tagihan ini.');
        }

        if ($invoice->isLunas()) {
            $this->redirectRoute('portal.invoice.show', $invoice, navigate: true);

            return;
        }

        $this->invoice = $invoice->load(['pelanggan', 'layananPelanggan.paketLayanan']);

        // Cek apakah sudah ada transaksi pending yang belum expired
        $this->loadTransaksiAktif();
    }

    public function loadTransaksiAktif(): void
    {
        $transaksi = TransaksiPaymentGateway::where('invoice_id', $this->invoice->id)
            ->where('status', StatusTransaksiGateway::Pending)
            ->where('expired_at', '>', now())
            ->latest('id')
            ->first();

        $this->transaksiAktif = $transaksi;

        if ($transaksi) {
            $this->channelTipe = $transaksi->channel === GatewayChannel::Qris ? 'qris' : 'va';
            if ($transaksi->channel_detail) {
                $this->bankCode = strtoupper($transaksi->channel_detail);
            }
        }
    }

    public function generatePembayaran(XenditPaymentService $paymentService): void
    {
        $this->validate([
            'channelTipe' => ['required', 'in:va,qris'],
            'bankCode' => ['required_if:channelTipe,va', 'string'],
        ]);

        try {
            if ($this->channelTipe === 'va') {
                $transaksi = $paymentService->buatVirtualAccount($this->invoice, $this->bankCode);
            } else {
                $transaksi = $paymentService->buatQris($this->invoice);
            }

            $this->transaksiAktif = $transaksi;
            Flux::toast(variant: 'success', text: 'Kode pembayaran berhasil diterbitkan. Silakan selesaikan pembayaran.');
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function gantiMetode(): void
    {
        $this->transaksiAktif = null;
    }

    public function cekStatus(XenditPaymentService $paymentService): void
    {
        $this->invoice->refresh();

        if ($this->invoice->isLunas()) {
            Flux::toast(variant: 'success', text: 'Pembayaran berhasil dikonfirmasi! Layanan internet Anda telah diperpanjang.');
            $this->redirectRoute('portal.invoice.show', $this->invoice, navigate: true);

            return;
        }

        if ($this->transaksiAktif) {
            $this->transaksiAktif->refresh();

            // Cek status langsung ke Xendit bila tombol ditekan manual
            $statusXendit = $paymentService->cekStatusTransaksi($this->transaksiAktif);

            if (($statusXendit['status'] ?? '') === 'SUCCEEDED' || ($statusXendit['status'] ?? '') === 'PAID') {
                $this->invoice->refresh();
                if ($this->invoice->isLunas()) {
                    Flux::toast(variant: 'success', text: 'Pembayaran terkonfirmasi lunas!');
                    $this->redirectRoute('portal.invoice.show', $this->invoice, navigate: true);
                }
            } else {
                Flux::toast(variant: 'info', text: 'Status: Menunggu Pembayaran. Silakan transfer sesuai instruksi.');
            }
        }
    }

    public function render(): View
    {
        $pengaturan = PengaturanGateway::getXenditSetting();
        $nominal = (float) $this->invoice->jumlah_setelah_promo;

        return view('livewire.portal.invoice.bayar', [
            'pengaturan' => $pengaturan,
            'feeVa' => $pengaturan->hitungFee('virtual_account', $nominal),
            'feeQris' => $pengaturan->hitungFee('qris', $nominal),
        ]);
    }
}
