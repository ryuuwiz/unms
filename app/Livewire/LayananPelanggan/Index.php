<?php

namespace App\Livewire\LayananPelanggan;

use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Layanan Pelanggan')]
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
        $layanan = LayananPelanggan::findOrFail($id);
        $this->authorize('delete', $layanan);
        $this->deletingId = $id;
    }

    public function deleteLayanan(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $layanan = LayananPelanggan::findOrFail($this->deletingId);
        $this->authorize('delete', $layanan);

        $layanan->delete();
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: 'Layanan berhasil dihapus.');
    }

    public function render(): View
    {
        $layanans = LayananPelanggan::query()
            ->with(['pelanggan', 'paketLayanan', 'router'])
            ->when($this->search, fn ($q) => $q->whereHas('pelanggan', fn ($pq) => $pq->search($this->search)))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15);

        return view('livewire.layanan-pelanggan.index', [
            'layanans' => $layanans,
            'statuses' => StatusLayanan::cases(),
        ]);
    }
}
