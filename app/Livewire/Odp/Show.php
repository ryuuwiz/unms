<?php

namespace App\Livewire\Odp;

use App\Enums\StatusOdpPort;
use App\Models\Odp;
use App\Models\OdpPort;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail ODP')]
class Show extends Component
{
    public int $odpId;

    public function mount(Odp $odp): void
    {
        $this->authorize('view', $odp);
        $this->odpId = $odp->id;
    }

    public function setPortStatus(int $portId, string $status): void
    {
        $port = OdpPort::where('odp_id', $this->odpId)->findOrFail($portId);
        $this->authorize('update', $port->odp);

        if ($port->layanan_pelanggan_id && $status === 'kosong') {
            Flux::toast(variant: 'danger', text: 'Port tidak dapat diubah ke Kosong karena masih terhubung ke layanan pelanggan.');

            return;
        }

        $enumStatus = StatusOdpPort::tryFrom($status);
        if (! $enumStatus) {
            return;
        }

        $port->update(['status' => $enumStatus]);

        Flux::toast(variant: 'success', text: "Status Port #{$port->nomor_port} diubah menjadi {$enumStatus->label()}.");
    }

    public function render(): View
    {
        $odp = Odp::with([
            'perumahan',
            'ports' => fn ($q) => $q->orderBy('nomor_port', 'asc')->with('layananPelanggan.pelanggan'),
        ])->findOrFail($this->odpId);

        $this->authorize('view', $odp);

        $totalPort = $odp->ports->count();
        $totalKosong = $odp->ports->where('status', StatusOdpPort::Kosong)->count();
        $totalTerpakai = $odp->ports->where('status', StatusOdpPort::Terpakai)->count();
        $totalRusak = $odp->ports->where('status', StatusOdpPort::Rusak)->count();

        return view('livewire.odp.show', [
            'odp' => $odp,
            'totalPort' => $totalPort,
            'totalKosong' => $totalKosong,
            'totalTerpakai' => $totalTerpakai,
            'totalRusak' => $totalRusak,
        ]);
    }
}
