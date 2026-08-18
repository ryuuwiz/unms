<?php

namespace App\Livewire\Wilayah\Perumahan;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
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

    public ?int $filterKotaId = null;

    public ?int $filterKecamatanId = null;

    public ?int $filterKelurahanId = null;

    public ?int $deletingId = null;

    public ?int $viewingMapId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKotaId(): void
    {
        $this->filterKecamatanId = null;
        $this->filterKelurahanId = null;
        $this->resetPage();
    }

    public function updatedFilterKecamatanId(): void
    {
        $this->filterKelurahanId = null;
        $this->resetPage();
    }

    public function updatingFilterKelurahanId(): void
    {
        $this->resetPage();
    }

    public function showMap(int $id): void
    {
        $this->viewingMapId = $id;
    }

    public function closeMap(): void
    {
        $this->viewingMapId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', new Kota);
        $this->deletingId = $id;
    }

    public function deletePerumahan(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $perumahan = Perumahan::withCount(['pelanggans', 'odps'])->findOrFail($this->deletingId);
        $this->authorize('delete', $perumahan->kelurahan->kecamatan->kota);

        if (! $perumahan->canBeDeleted()) {
            $this->deletingId = null;
            Flux::toast(
                variant: 'danger',
                text: "Perumahan {$perumahan->nama_perumahan} tidak dapat dihapus karena masih memiliki {$perumahan->pelanggans_count} pelanggan atau {$perumahan->odps_count} ODP terdaftar."
            );

            return;
        }

        $nama = $perumahan->nama_perumahan;
        $perumahan->delete();

        $this->deletingId = null;
        Flux::toast(variant: 'success', text: "Perumahan {$nama} berhasil dihapus.");
    }

    public function render(): View
    {
        $perumahans = Perumahan::query()
            ->with(['kelurahan.kecamatan.kota'])
            ->withCount(['pelanggans', 'odps'])
            ->when($this->filterKotaId, function ($q) {
                $q->whereHas('kelurahan.kecamatan', fn ($kq) => $kq->where('kota_id', $this->filterKotaId));
            })
            ->when($this->filterKecamatanId, function ($q) {
                $q->whereHas('kelurahan', fn ($lq) => $lq->where('kecamatan_id', $this->filterKecamatanId));
            })
            ->when($this->filterKelurahanId, fn ($q) => $q->where('kelurahan_id', $this->filterKelurahanId))
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('nama_perumahan', 'like', "%{$this->search}%")
                        ->orWhere('singkatan', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('nama_perumahan')
            ->paginate(15);

        $kotas = Kota::orderBy('nama_kota')->get();

        $kecamatans = $this->filterKotaId
            ? Kecamatan::where('kota_id', $this->filterKotaId)->orderBy('nama_kecamatan')->get()
            : collect();

        $kelurahans = $this->filterKecamatanId
            ? Kelurahan::where('kecamatan_id', $this->filterKecamatanId)->orderBy('nama_kelurahan')->get()
            : collect();

        $activeMapPerumahan = $this->viewingMapId
            ? Perumahan::with(['kelurahan.kecamatan.kota'])->find($this->viewingMapId)
            : null;

        return view('livewire.wilayah.perumahan.index', [
            'perumahans' => $perumahans,
            'kotas' => $kotas,
            'kecamatans' => $kecamatans,
            'kelurahans' => $kelurahans,
            'activeMapPerumahan' => $activeMapPerumahan,
        ]);
    }
}
