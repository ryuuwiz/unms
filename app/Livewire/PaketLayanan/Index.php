<?php

namespace App\Livewire\PaketLayanan;

use App\Enums\StatusPaket;
use App\Models\PaketLayanan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Paket Layanan')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function deletePaket(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $paket = PaketLayanan::findOrFail($this->deletingId);
        $this->authorize('delete', $paket);

        if ($paket->layanans()->exists()) {
            Flux::toast(variant: 'danger', text: 'Paket layanan sedang digunakan oleh pelanggan aktif dan tidak dapat dihapus.');
            $this->deletingId = null;

            return;
        }

        $paket->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Paket layanan berhasil dihapus.');
    }

    public function toggleStatus(int $id): void
    {
        $paket = PaketLayanan::findOrFail($id);
        $this->authorize('update', $paket);

        $newStatus = $paket->status === StatusPaket::Aktif
            ? StatusPaket::Nonaktif
            : StatusPaket::Aktif;

        $paket->update(['status' => $newStatus]);

        Flux::toast(variant: 'success', text: "Status paket diubah menjadi {$newStatus->label()}.");
    }

    public function render(): View
    {
        $pakets = PaketLayanan::query()
            ->with('profilBandwidth')
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15);

        return view('livewire.paket-layanan.index', [
            'pakets' => $pakets,
            'statuses' => StatusPaket::cases(),
        ]);
    }
}
