<?php

namespace App\Livewire\Pelanggan;

use App\Models\Pelanggan;
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

    public function mount(Pelanggan $pelanggan): void
    {
        $this->authorize('view', $pelanggan);
        $this->pelangganId = $pelanggan->id;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        $pelanggan = Pelanggan::with(['pembuat', 'perumahan', 'layanans.paketLayanan', 'layanans.router'])
            ->findOrFail($this->pelangganId);

        $activityLogs = Activity::forSubject($pelanggan)
            ->with('causer')
            ->latest()
            ->get();

        return view('livewire.pelanggan.show', [
            'pelanggan' => $pelanggan,
            'activityLogs' => $activityLogs,
        ]);
    }
}
