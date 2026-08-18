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

        $kota = Kota::withCount('kecamatans')->findOrFail($this->deletingId);
        $this->authorize('delete', $kota);

        if (! $kota->canBeDeleted()) {
            $this->deletingId = null;
            Flux::toast(
                variant: 'danger',
                text: "Kota {$kota->nama_kota} tidak dapat dihapus karena masih memiliki {$kota->kecamatans_count} kecamatan terkait."
            );

            return;
        }

        $nama = $kota->nama_kota;
        $kota->delete();

        $this->deletingId = null;
        Flux::toast(variant: 'success', text: "Kota {$nama} berhasil dihapus.");
    }

    public function render(): View
    {
        $kotas = Kota::query()
            ->when($this->search, fn ($q) => $q->where('nama_kota', 'like', "%{$this->search}%"))
            ->withCount(['kecamatans', 'kelurahans'])
            ->orderBy('nama_kota')
            ->paginate(15);

        return view('livewire.wilayah.kota.index', ['kotas' => $kotas]);
    }
}
