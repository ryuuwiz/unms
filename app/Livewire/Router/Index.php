<?php

namespace App\Livewire\Router;

use App\Enums\StatusRouter;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Router')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
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
        $this->modal('confirm-delete')->show();
    }

    public function deleteRouter(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $router = Router::findOrFail($this->deletingId);
        $this->authorize('delete', $router);

        if ($router->layanans()->exists() || $router->ipPools()->exists()) {
            $this->modal('confirm-delete')->close();
            Flux::toast(variant: 'danger', text: 'Router masih memiliki data relasi (layanan/IP pool) dan tidak dapat dihapus.');
            $this->deletingId = null;

            return;
        }

        $router->delete();
        $this->modal('confirm-delete')->close();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Router berhasil dihapus.');
    }

    public function toggleStatus(int $routerId): void
    {
        $router = Router::findOrFail($routerId);
        $this->authorize('update', $router);

        $newStatus = $router->status_koneksi === StatusRouter::Online
            ? StatusRouter::Offline
            : StatusRouter::Online;

        $router->update(['status_koneksi' => $newStatus]);

        Flux::toast(variant: 'success', text: 'Status router berhasil diubah.');
    }

    public function testConnection(int $routerId, MikrotikService $mikrotikService): void
    {
        $router = Router::findOrFail($routerId);
        $this->authorize('update', $router);

        try {
            $result = $mikrotikService->testConnection($router, 4);
            $rosVersion = $router->routeros_version ? " (v{$router->routeros_version})" : '';
            Flux::toast(
                variant: 'success',
                text: "Koneksi ke {$router->nama_router} berhasil{$rosVersion}."
            );
        } catch (\Throwable $e) {
            Flux::toast(
                variant: 'danger',
                text: "Gagal terhubung ke {$router->nama_router}: {$e->getMessage()}"
            );
        }
    }

    public function provisionRouter(int $routerId, MikrotikService $mikrotikService): void
    {
        $router = Router::findOrFail($routerId);
        $this->authorize('update', $router);

        try {
            $result = $mikrotikService->provisionRouterFull($router, cleanOrphans: true);
            $details = $result['details'] ?? [];
            $poolSynced = $details['ip_pools']['synced'] ?? 0;
            $profileSynced = $details['profiles']['synced'] ?? 0;
            $secretRecovered = $details['secrets']['recovered'] ?? 0;
            $orphansDeleted = $details['orphans']['deleted'] ?? 0;

            $orphanText = $orphansDeleted > 0 ? ", Orphan: {$orphansDeleted} dibersihkan" : '';

            Flux::toast(
                variant: 'success',
                text: "Provisi {$router->nama_router} sukses! (Pool: {$poolSynced}, Profil: {$profileSynced}, Secret: {$secretRecovered}{$orphanText})"
            );
        } catch (\Throwable $e) {
            Flux::toast(
                variant: 'danger',
                text: "Gagal provisi {$router->nama_router}: {$e->getMessage()}"
            );
        }
    }

    public function render(): View
    {
        $routers = Router::query()
            ->withCount(['ipPools', 'layanans'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('nama_router', 'like', "%{$this->search}%")
                    ->orWhere('ip_address', 'like', "%{$this->search}%")
                    ->orWhere('deskripsi', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus, fn ($q) => $q->where('status_koneksi', $this->filterStatus))
            ->latest()
            ->paginate(15);

        return view('livewire.router.index', [
            'routers' => $routers,
            'statuses' => StatusRouter::cases(),
        ]);
    }
}
