<?php

namespace App\Livewire\Portal\Invoice;

use App\Models\Invoice;
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

    public function render(): View
    {
        return view('livewire.portal.invoice.show');
    }
}
