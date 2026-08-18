<?php

namespace App\Livewire\Wilayah\Kecamatan;

use App\Models\Kecamatan;
use App\Models\Kota;
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

    public ?int $filterKotaId = null;

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKotaId(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', new Kota);
        $this->deletingId = $id;
    }

    public function deleteKecamatan(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $kecamatan = Kecamatan::withCount(['kelurahans'])->findOrFail($this->deletingId);
        $this->authorize('delete', $kecamatan->kota);

        if (! $kecamatan->canBeDeleted()) {
            $this->deletingId = null;
            Flux::toast(
                variant: 'danger',
                text: "Kecamatan {$kecamatan->nama_kecamatan} tidak dapat dihapus karena masih memiliki {$kecamatan->kelurahans_count} kelurahan terkait."
            );

            return;
        }

        $nama = $kecamatan->nama_kecamatan;
        $kecamatan->delete();

        $this->deletingId = null;
        Flux::toast(variant: 'success', text: "Kecamatan {$nama} berhasil dihapus.");
    }

    public function render(): View
    {
        $kecamatans = Kecamatan::query()
            ->with('kota')
            ->withCount('kelurahans')
            ->when($this->filterKotaId, fn ($q) => $q->where('kota_id', $this->filterKotaId))
            ->when($this->search, fn ($q) => $q->where('nama_kecamatan', 'like', "%{$this->search}%"))
            ->orderBy('nama_kecamatan')
            ->paginate(15);

        $kotas = Kota::orderBy('nama_kota')->get();

        return view('livewire.wilayah.kecamatan.index', [
            'kecamatans' => $kecamatans,
            'kotas' => $kotas,
        ]);
    }
}
