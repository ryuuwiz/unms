<?php

namespace App\Livewire\Routers;

use App\Enums\RouterStatus;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Routers')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $routerId): void
    {
        $router = Router::findOrFail($routerId);

        $newStatus = $router->status === RouterStatus::Online
            ? RouterStatus::Offline
            : RouterStatus::Online;

        $router->update(['status' => $newStatus]);

        Flux::toast(variant: 'success', text: 'Status router berhasil diubah.');
    }

    public function render(): View
    {
        $routers = Router::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('ip_address', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15);

        return view('livewire.routers.index', [
            'routers' => $routers,
            'statuses' => RouterStatus::cases(),
        ]);
    }
}
