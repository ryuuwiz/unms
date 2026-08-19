<?php

namespace App\Livewire\Portal\Invoice;

use App\Models\AkunPelanggan;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Daftar Tagihan & Riwayat Pembayaran')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all';

    public function setStatusFilter(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();
        $pelanggan = $akun->pelanggan;

        $query = Invoice::where('pelanggan_id', $pelanggan->id)
            ->with(['layananPelanggan.paketLayanan', 'promo', 'transaksiPaymentGateways', 'pembayarans'])
            ->latest('tanggal_terbit');

        if ($this->status === 'menunggu_pembayaran') {
            $query->menungguPembayaran();
        } elseif ($this->status === 'lunas') {
            $query->lunas();
        }

        $invoices = $query->paginate(10);

        return view('livewire.portal.invoice.index', [
            'invoices' => $invoices,
            'pelanggan' => $pelanggan,
        ]);
    }
}
