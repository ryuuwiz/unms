<?php

namespace App\Livewire\Wilayah\Perumahan;

use App\Models\Perumahan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Perumahan')]
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
        $this->deletingId = $id;
    }

    public function deletePerumahan(): void
    {
        if (! $this->deletingId) {
            return;
        }
        Perumahan::findOrFail($this->deletingId)->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Data perumahan berhasil dihapus.');
    }

    public function render(): View
    {
        $perumahans = Perumahan::query()
            ->with('kelurahan.kecamatan')
            ->when($this->search, fn ($q) => $q->where('nama_perumahan', 'like', "%{$this->search}%"))
            ->orderBy('nama_perumahan')
            ->paginate(20);

        return view('livewire.wilayah.perumahan.index', compact('perumahans'));
    }
}
