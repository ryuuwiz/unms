<?php

namespace App\Livewire\IpPublik;

use App\Models\IpPublik;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data IP Publik')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterRouter = '';

    public string $filterStatus = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRouter(): void
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

    public function deleteIpPublik(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $ipPublik = IpPublik::findOrFail($this->deletingId);
        $this->authorize('delete', $ipPublik);

        if (! $ipPublik->isTersedia()) {
            $this->deletingId = null;
            Flux::toast(variant: 'danger', text: "IP Publik {$ipPublik->alamat_ip} sedang dipakai layanan dan tidak dapat dihapus. Lepas dari layanan terlebih dahulu.");

            return;
        }

        $alamat = $ipPublik->alamat_ip;
        $ipPublik->delete();
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: "IP Publik {$alamat} berhasil dihapus.");
    }

    public function render(): View
    {
        $items = IpPublik::query()
            ->with(['router', 'layananPelanggan.pelanggan'])
            ->when($this->search, fn ($q) => $q->where('alamat_ip', 'like', "%{$this->search}%"))
            ->when($this->filterRouter, fn ($q) => $q->where('router_id', $this->filterRouter))
            ->when($this->filterStatus === 'tersedia', fn ($q) => $q->whereNull('layanan_pelanggan_id'))
            ->when($this->filterStatus === 'terpakai', fn ($q) => $q->whereNotNull('layanan_pelanggan_id'))
            ->orderBy('alamat_ip')
            ->paginate(15);

        return view('livewire.ip-publik.index', [
            'items' => $items,
            'routers' => Router::orderBy('nama_router')->get(),
            'itemToDelete' => $this->deletingId ? IpPublik::find($this->deletingId) : null,
        ]);
    }
}
