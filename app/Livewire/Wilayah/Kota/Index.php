<?php

namespace App\Livewire\Wilayah\Kota;

use App\Models\Kota;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Kota')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', new Kota);
        $this->deletingId = $id;
    }

    public function deleteKota(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $kota = Kota::findOrFail($this->deletingId);
        $this->authorize('delete', $kota);

        $kota->delete();

        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Data kota berhasil dihapus.');
    }

    public function render(): View
    {
        $kotas = Kota::query()
            ->when($this->search, fn ($q) => $q->where('nama_kota', 'like', "%{$this->search}%"))
            ->withCount('kecamatans')
            ->orderBy('nama_kota')
            ->paginate(20);

        return view('livewire.wilayah.kota.index', ['kotas' => $kotas]);
    }
}
