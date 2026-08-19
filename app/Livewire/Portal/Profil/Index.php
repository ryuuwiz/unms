<?php

namespace App\Livewire\Portal\Profil;

use App\Models\AkunPelanggan;
use App\Models\Pelanggan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Profil & Langganan Pelanggan')]
class Index extends Component
{
    public function render(): View
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        /** @var Pelanggan $pelanggan */
        $pelanggan = $akun->pelanggan()
            ->with(['perumahan.kelurahan.kecamatan.kota', 'layanans.paketLayanan.profilBandwidth'])
            ->firstOrFail();

        return view('livewire.portal.profil.index', [
            'akun' => $akun,
            'pelanggan' => $pelanggan,
        ]);
    }
}
