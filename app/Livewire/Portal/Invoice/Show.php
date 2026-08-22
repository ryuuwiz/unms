<?php

namespace App\Livewire\Portal\Invoice;

use App\Models\Invoice;
use App\Services\Xendit\XenditPaymentService;
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

    public function mount(Invoice $invoice): void
    {
        $pelangganId = Auth::guard('pelanggan')->user()->pelanggan_id;

        if ($invoice->pelanggan_id !== $pelangganId) {
            abort(403, 'Anda tidak memiliki akses ke tagihan ini.');
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
     * Arahkan pelanggan ke link hosted payment page Xendit.
     */
    public function bayar(XenditPaymentService $paymentService): mixed
    {
        $this->invoice->refresh();

        if ($this->invoice->isLunas()) {
            Flux::toast(variant: 'success', text: 'Tagihan ini telah lunas.');

            return null;
        }

        try {
            // Jika belum memiliki link Xendit aktif atau sudah expired, generate baru
            if (! $this->invoice->hasActiveXenditInvoice()) {
                $paymentService->buatInvoice($this->invoice, forceRegenerate: true);
                $this->invoice->refresh();
            }

            if (! empty($this->invoice->xendit_invoice_url)) {
                return redirect()->away($this->invoice->xendit_invoice_url);
            }

            Flux::toast(variant: 'danger', text: 'Gagal memuat link pembayaran Xendit.');
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
