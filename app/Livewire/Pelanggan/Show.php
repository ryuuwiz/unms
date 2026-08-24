<?php

namespace App\Livewire\Pelanggan;

use App\Models\Pelanggan;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
#[Title('Detail Pelanggan')]
class Show extends Component
{
    public int $pelangganId;

    public string $activeTab = 'overview';

    /**
     * Cache status realtime PPP per ID layanan pelanggan.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $pppStatuses = [];

    public function mount(Pelanggan $pelanggan, MikrotikService $mikrotikService): void
    {
        $this->authorize('view', $pelanggan);
        $this->pelangganId = $pelanggan->id;
        $this->loadPppStatuses($pelanggan, $mikrotikService);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function refreshPppStatus(MikrotikService $mikrotikService): void
    {
        $pelanggan = Pelanggan::with(['layanans.router', 'layanans.paketLayanan.profilBandwidth'])
            ->findOrFail($this->pelangganId);

        $this->loadPppStatuses($pelanggan, $mikrotikService);

        Flux::toast(
            text: 'Status realtime PPP berhasil diperbarui dari MikroTik.',
            variant: 'success',
        );
    }

    public function loadPppStatuses(Pelanggan $pelanggan, MikrotikService $mikrotikService): void
    {
        $this->pppStatuses = [];

        $layanans = $pelanggan->relationLoaded('layanans') && $pelanggan->layanans->isNotEmpty()
            ? $pelanggan->layanans
            : $pelanggan->layanans()->with(['router', 'paketLayanan.profilBandwidth'])->get();

        foreach ($layanans as $layanan) {
            if ($layanan->router && ! empty($layanan->ppp_username)) {
                $this->pppStatuses[$layanan->id] = $mikrotikService->getPppStatus(
                    $layanan->router,
                    $layanan->ppp_username
                );
            }
        }
    }

    public function render(): View
    {
        $pelanggan = Pelanggan::with([
            'pembuat',
            'perumahan.kelurahan.kecamatan.kota',
            'layanans.paketLayanan.profilBandwidth',
            'layanans.router',
            'layanans.ipPool',
            'layanans.odpPort.odp',
        ])->findOrFail($this->pelangganId);

        $activityLogs = Activity::forSubject($pelanggan)
            ->with('causer')
            ->latest()
            ->get();

        return view('livewire.pelanggan.show', [
            'pelanggan' => $pelanggan,
            'activityLogs' => $activityLogs,
            'pppStatuses' => $this->pppStatuses,
        ]);
    }
}
