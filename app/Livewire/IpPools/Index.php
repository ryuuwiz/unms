<?php

namespace App\Livewire\IpPools;

use App\Models\IpPool;
use App\Models\Router;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('IP Pools')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterRouter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRouter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $pools = IpPool::query()
            ->with('router')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('ip_network', 'like', "%{$this->search}%");
            }))
            ->when($this->filterRouter, fn ($q) => $q->where('router_id', $this->filterRouter))
            ->latest()
            ->paginate(15);

        $routers = Router::orderBy('name')->get();

        return view('livewire.ip-pools.index', [
            'pools' => $pools,
            'routers' => $routers,
        ]);
    }
}
