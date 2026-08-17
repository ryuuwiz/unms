<?php

namespace App\Livewire\Wilayah\Kelurahan;

use App\Models\Kelurahan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Kelurahan')]
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

    public function deleteKelurahan(): void
    {
        if (! $this->deletingId) {
            return;
        }
        Kelurahan::findOrFail($this->deletingId)->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Data kelurahan berhasil dihapus.');
    }

    public function render(): View
    {
        $kelurahans = Kelurahan::query()
            ->with('kecamatan.kota')
            ->when($this->search, fn ($q) => $q->where('nama_kelurahan', 'like', "%{$this->search}%"))
            ->orderBy('nama_kelurahan')
            ->paginate(20);

        return view('livewire.wilayah.kelurahan.index', compact('kelurahans'));
    }
}
