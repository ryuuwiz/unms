<?php

namespace App\Livewire\Wilayah\Kelurahan;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
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

    public ?int $filterKotaId = null;

    public ?int $filterKecamatanId = null;

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKotaId(): void
    {
        $this->filterKecamatanId = null;
        $this->resetPage();
    }

    public function updatingFilterKecamatanId(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', new Kota);
        $this->deletingId = $id;
    }

    public function deleteKelurahan(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $kelurahan = Kelurahan::withCount(['perumahans'])->findOrFail($this->deletingId);
        $this->authorize('delete', $kelurahan->kecamatan->kota);

        if (! $kelurahan->canBeDeleted()) {
            $this->deletingId = null;
            Flux::toast(
                variant: 'danger',
                text: "Kelurahan {$kelurahan->nama_kelurahan} tidak dapat dihapus karena masih memiliki {$kelurahan->perumahans_count} perumahan terkait."
            );

            return;
        }

        $nama = $kelurahan->nama_kelurahan;
        $kelurahan->delete();

        $this->deletingId = null;
        Flux::toast(variant: 'success', text: "Kelurahan {$nama} berhasil dihapus.");
    }

    public function render(): View
    {
        $kelurahans = Kelurahan::query()
            ->with(['kecamatan.kota'])
            ->withCount('perumahans')
            ->when($this->filterKotaId, function ($q) {
                $q->whereHas('kecamatan', fn ($kq) => $kq->where('kota_id', $this->filterKotaId));
            })
            ->when($this->filterKecamatanId, fn ($q) => $q->where('kecamatan_id', $this->filterKecamatanId))
            ->when($this->search, fn ($q) => $q->where('nama_kelurahan', 'like', "%{$this->search}%"))
            ->orderBy('nama_kelurahan')
            ->paginate(15);

        $kotas = Kota::orderBy('nama_kota')->get();

        $kecamatans = $this->filterKotaId
            ? Kecamatan::where('kota_id', $this->filterKotaId)->orderBy('nama_kecamatan')->get()
            : Kecamatan::orderBy('nama_kecamatan')->get();

        return view('livewire.wilayah.kelurahan.index', [
            'kelurahans' => $kelurahans,
            'kotas' => $kotas,
            'kecamatans' => $kecamatans,
        ]);
    }
}
