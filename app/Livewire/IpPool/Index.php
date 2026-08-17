<?php

namespace App\Livewire\IpPool;

use App\Models\IpPool;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data IP Pool')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterRouter = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRouter(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function deleteIpPool(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $pool = IpPool::findOrFail($this->deletingId);
        $this->authorize('delete', $pool);

        $pool->delete();
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: 'IP Pool berhasil dihapus.');
    }

    public function render(): View
    {
        $pools = IpPool::query()
            ->with('router')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('nama_pool', 'like', "%{$this->search}%")
                    ->orWhere('ip_network', 'like', "%{$this->search}%");
            }))
            ->when($this->filterRouter, fn ($q) => $q->where('router_id', $this->filterRouter))
            ->latest()
            ->paginate(15);

        $routers = Router::orderBy('nama_router')->get();

        return view('livewire.ip-pool.index', [
            'pools' => $pools,
            'routers' => $routers,
        ]);
    }
}
