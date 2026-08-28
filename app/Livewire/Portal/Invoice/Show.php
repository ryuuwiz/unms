<?php

namespace App\Livewire\Portal\Invoice;

use App\Models\Invoice;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Exception;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Rincian Invoice')]
class Show extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice, PaymentGatewayManager $paymentManager): void
    {
        $pelangganId = Auth::guard('pelanggan')->user()->pelanggan_id;

        if ($invoice->pelanggan_id !== $pelangganId) {
            abort(403, 'Anda tidak memiliki akses ke tagihan ini.');
        }

        // Sinkronisasi otomatis dengan payment gateway jika masih menunggu pembayaran
        // (Sangat berguna saat pelanggan kembali di-redirect dari halaman checkout gateway)
        if ($invoice->isMenungguPembayaran() && (! empty($invoice->payment_gateway_id) || ! empty($invoice->xendit_invoice_id))) {
            $paymentManager->sinkronkanStatus($invoice);
            $invoice->refresh();
        }

        $this->invoice = $invoice->load([
            'pelanggan',
            'layananPelanggan.paketLayanan.profilBandwidth',
            'promo',
            'pembayarans',
            'transaksiPaymentGateways' => fn ($q) => $q->latest(),
        ]);
    }

    /**
     * Cek dan sinkronisasikan status pembayaran terbaru dari gateway.
     */
    public function sinkronkanStatus(PaymentGatewayManager $paymentManager): void
    {
        try {
            $paymentManager->sinkronkanStatus($this->invoice);
            $this->invoice->refresh();

            if ($this->invoice->isLunas()) {
                Flux::toast(variant: 'success', text: 'Pembayaran berhasil terkonfirmasi! Tagihan telah lunas.');
            } else {
                Flux::toast(variant: 'info', text: 'Status tagihan: Menunggu pembayaran.');
            }
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: 'Gagal memperbarui status: '.$e->getMessage());
        }
    }

    /**
     * Arahkan pelanggan ke tautan hosted payment page resmi payment gateway.
     */
    public function bayar(PaymentGatewayManager $paymentManager): mixed
    {
        $this->invoice->refresh();

        if ($this->invoice->isLunas()) {
            Flux::toast(variant: 'success', text: 'Tagihan ini telah lunas.');

            return null;
        }

        // Cek sinkronisasi terlebih dahulu jika sudah dibayar di tab/jendela lain
        if (! empty($this->invoice->payment_gateway_id) || ! empty($this->invoice->xendit_invoice_id)) {
            $paymentManager->sinkronkanStatus($this->invoice);
            $this->invoice->refresh();

            if ($this->invoice->isLunas()) {
                Flux::toast(variant: 'success', text: 'Tagihan ini telah terkonfirmasi lunas!');

                return null;
            }
        }

        try {
            // Jika belum memiliki link payment gateway aktif atau sudah expired, generate baru
            if (! $this->invoice->hasActivePaymentLink()) {
                $paymentManager->buatPaymentLink($this->invoice, forceRegenerate: true);
                $this->invoice->refresh();
            }

            $paymentUrl = $this->invoice->payment_gateway_url ?: $this->invoice->xendit_invoice_url;

            if (! empty($paymentUrl)) {
                return redirect()->away($paymentUrl);
            }

            Flux::toast(variant: 'danger', text: 'Gagal memuat tautan pembayaran gateway.');
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: 'Gagal memproses pembayaran: '.$e->getMessage());
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.portal.invoice.show');
    }
}
