<?php

namespace App\Livewire\Portal\Invoice;

use App\Livewire\Portal\Invoice\Concerns\AuthorizesInvoiceAccess;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Exception;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Pembayaran Tagihan')]
class Bayar extends Component
{
    use AuthorizesInvoiceAccess;

    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->authorizeAksesTagihan($invoice);

        if ($invoice->isLunas() || $invoice->isDibatalkan()) {
            if ($invoice->isDibatalkan()) {
                Flux::toast(variant: 'warning', text: 'Tagihan ini telah dibatalkan dan tidak dapat dibayar.');
            }

            $this->redirectRoute('portal.invoice.show', $invoice, navigate: true);

            return;
        }

        $this->invoice = $invoice->load(['pelanggan', 'layananPelanggan.paketLayanan']);
    }

    /**
     * Terbitkan (atau pakai ulang) tautan hosted payment page resmi lalu arahkan ke sana --
     * pemilihan channel (VA/QRIS/E-wallet) dilakukan pelanggan di halaman Xendit itu sendiri,
     * lihat PaymentGatewayManager::resolvePaymentUrl().
     */
    public function lanjutkanPembayaran(PaymentGatewayManager $paymentManager): mixed
    {
        try {
            $paymentUrl = $paymentManager->resolvePaymentUrl($this->invoice);

            if ($paymentUrl) {
                return redirect()->away($paymentUrl);
            }

            if ($this->invoice->refresh()->isLunas()) {
                Flux::toast(variant: 'success', text: 'Tagihan ini telah lunas.');
                $this->redirectRoute('portal.invoice.show', $this->invoice, navigate: true);

                return null;
            }

            Flux::toast(variant: 'danger', text: 'Gagal memuat tautan pembayaran gateway.');
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: 'Gagal memproses pembayaran: '.$e->getMessage());
        }

        return null;
    }

    public function render(): View
    {
        $pengaturan = PengaturanGateway::getXenditSetting();
        $nominal = (float) $this->invoice->jumlah_setelah_promo;

        return view('livewire.portal.invoice.bayar', [
            'feeVa' => $pengaturan->hitungFee('virtual_account', $nominal),
            'feeQris' => $pengaturan->hitungFee('qris', $nominal),
        ]);
    }
}
