<?php

namespace App\Livewire\ProfilBandwidth;

use App\Enums\StatusRouter;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Profil Bandwidth')]
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

    public function deleteProfilBandwidth(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $profil = ProfilBandwidth::findOrFail($this->deletingId);
        $this->authorize('delete', $profil);

        if ($profil->pakets()->exists()) {
            Flux::toast(variant: 'danger', text: 'Profil bandwidth masih digunakan oleh paket layanan dan tidak dapat dihapus.');
            $this->deletingId = null;

            return;
        }

        $profil->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Profil bandwidth berhasil dihapus.');
    }

    public function syncAllToRouters(MikrotikService $service): void
    {
        $this->authorize('viewAny', ProfilBandwidth::class);

        $routers = Router::where('status_koneksi', StatusRouter::Online)->get();

        if ($routers->isEmpty()) {
            Flux::toast(variant: 'warning', text: 'Tidak ada router dengan status Online untuk disinkronisasikan.');

            return;
        }

        $successRouters = 0;
        $totalSynced = 0;
        $errors = [];

        foreach ($routers as $router) {
            try {
                $res = $service->syncAllBandwidthProfiles($router);
                $successRouters++;
                $totalSynced += $res['synced'];
                if (! empty($res['errors'])) {
                    $errors = array_merge($errors, $res['errors']);
                }
            } catch (\Throwable $e) {
                $errors[] = "{$router->nama_router}: {$e->getMessage()}";
            }
        }

        if (empty($errors)) {
            Flux::toast(
                variant: 'success',
                text: "Berhasil sinkronisasi {$totalSynced} profil bandwidth (bps biner) ke {$successRouters} router online."
            );
        } else {
            Flux::toast(
                variant: 'warning',
                text: 'Sinkronisasi selesai dengan beberapa catatan pada router. Periksa log integrasi.'
            );
        }
    }

    public function render(): View
    {
        $profils = ProfilBandwidth::query()
            ->when($this->search, fn ($q) => $q->where('nama_bandwidth', 'like', "%{$this->search}%"))
            ->withCount('pakets')
            ->orderBy('max_limit_tx')
            ->paginate(15);

        return view('livewire.profil-bandwidth.index', ['profils' => $profils]);
    }
}
