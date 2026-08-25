<?php

namespace App\Livewire\Maps;

use App\Enums\StatusPelanggan;
use App\Models\Odp;
use App\Models\Pelanggan;
use App\Models\Perumahan;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Maps Lokasi')]
class Lokasi extends Component
{
    public bool $layerPelanggan = true;

    public bool $layerLayanan = false;

    public bool $layerOdp = true;

    public bool $layerPerumahan = true;

    public bool $layerCoverage = true;

    public string $statusFilter = 'semua';

    public string $perumahanFilter = '';

    public function render(): View
    {
        $this->authorize('viewAny', Pelanggan::class);

        $totalPelanggan = Pelanggan::whereNotNull('latitude')->whereNotNull('longitude')->count();
        $totalOdp = Odp::whereNotNull('latitude')->whereNotNull('longitude')->count();
        $totalPerumahan = Perumahan::whereNotNull('latitude')->whereNotNull('longitude')->count();

        return view('livewire.maps.lokasi', [
            'perumahans' => Perumahan::orderBy('nama_perumahan')->get(),
            'statuses' => StatusPelanggan::cases(),
            'totalPelanggan' => $totalPelanggan,
            'totalOdp' => $totalOdp,
            'totalPerumahan' => $totalPerumahan,
        ]);
    }
}
