<?php

namespace App\Livewire\Portal;

use App\Models\AkunPelanggan;
use App\Models\Invoice;
use App\Models\Pelanggan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Dashboard Portal Pelanggan')]
class Dashboard extends Component
{
    public function render(): View
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        /** @var Pelanggan $pelanggan */
        $pelanggan = $akun->pelanggan()
            ->with(['perumahan', 'layanans.paketLayanan.profilBandwidth'])
            ->firstOrFail();

        $layanans = $pelanggan->layanans;

        $unpaidInvoices = Invoice::where('pelanggan_id', $pelanggan->id)
            ->menungguPembayaran()
            ->with(['layananPelanggan.paketLayanan', 'promo', 'transaksiPaymentGateways'])
            ->orderBy('tanggal_jatuh_tempo')
            ->get();

        $recentPaidInvoices = Invoice::where('pelanggan_id', $pelanggan->id)
            ->lunas()
            ->with(['layananPelanggan.paketLayanan', 'pembayarans'])
            ->latest('tanggal_lunas')
            ->limit(5)
            ->get();

        return view('livewire.portal.dashboard', [
            'pelanggan' => $pelanggan,
            'layanans' => $layanans,
            'unpaidInvoices' => $unpaidInvoices,
            'recentPaidInvoices' => $recentPaidInvoices,
        ]);
    }
}
