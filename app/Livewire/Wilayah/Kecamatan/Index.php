<?php

namespace App\Livewire\Wilayah\Kecamatan;

use App\Models\Kecamatan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Kecamatan')]
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

    public function deleteKecamatan(): void
    {
        if (! $this->deletingId) {
            return;
        }
        Kecamatan::findOrFail($this->deletingId)->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Data kecamatan berhasil dihapus.');
    }

    public function render(): View
    {
        $kecamatans = Kecamatan::query()
            ->with('kota')
            ->when($this->search, fn ($q) => $q->where('nama_kecamatan', 'like', "%{$this->search}%"))
            ->orderBy('nama_kecamatan')
            ->paginate(20);

        return view('livewire.wilayah.kecamatan.index', compact('kecamatans'));
    }
}
