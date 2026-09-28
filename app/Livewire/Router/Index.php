<?php

namespace App\Livewire\Router;

use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Livewire\Concerns\ProvisionsRouter;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Router')]
class Index extends Component
{
    use ProvisionsRouter;
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $deletingId = null;

    public string $deleteStrategy = 'move';

    public ?int $targetRouterId = null;

    public bool $confirmForceDelete = false;

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
        $firstOther = Router::where('id', '!=', $id)->first();
        $this->targetRouterId = $firstOther?->id;
        $this->confirmForceDelete = false;

        $this->modal('confirm-delete-router')->show();
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
        $this->targetRouterId = null;
        $this->confirmForceDelete = false;
    }

    public function deleteRouter(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $router = Router::withCount(['layanans', 'ipPools'])->find($this->deletingId);
        if (! $router) {
            $this->deletingId = null;
            $this->modal('confirm-delete-router')->close();

            return;
        }

        $this->authorize('delete', $router);

        // IP Publik yang terpasang adalah add-on berbayar milik layanan: lepas dulu dari layanan, jangan hilang diam-diam.
        $ipPublikTerpasang = IpPublik::where('router_id', $router->id)->whereNotNull('layanan_pelanggan_id')->count();
        if ($ipPublikTerpasang > 0) {
            Flux::toast(variant: 'danger', text: "Router {$router->nama_router} masih memiliki {$ipPublikTerpasang} IP Publik yang terpasang ke layanan. Lepas dari layanan terlebih dahulu (Edit Data Registrasi Billing).");

            return;
        }

        if ($router->layanans()->withTrashed()->exists()) {
            $otherRoutersCount = Router::where('id', '!=', $router->id)->count();

            // Jika admin memilih untuk force delete atau tidak ada router lain yang tersedia
            if ($this->confirmForceDelete || $otherRoutersCount === 0) {
                DB::transaction(function () use ($router) {
                    LayananPelanggan::withTrashed()
                        ->where('router_id', $router->id)
                        ->forceDelete();

                    $router->routerPakets()->delete();
                    $router->ipPools()->delete();
                    $router->jobLogs()->delete();
                    $router->delete();
                });

                $nama = $router->nama_router;
                $this->deletingId = null;
                $this->modal('confirm-delete-router')->close();
                Flux::toast(variant: 'success', text: "Router {$nama} beserta seluruh data layanan terkait berhasil dihapus permanen.");

                return;
            }

            // Opsi pemindahan ke router lain
            $targetRouter = $this->targetRouterId
                ? Router::where('id', '!=', $router->id)->find($this->targetRouterId)
                : Router::where('id', '!=', $router->id)->first();

            if (! $targetRouter) {
                Flux::toast(variant: 'danger', text: 'Pilih router tujuan untuk memindahkan data layanan pelanggan.');

                return;
            }

            // Router tujuan harus menjadi Router Paket untuk setiap paket yang dipindahkan (ADR-0063).
            $paketBelumTerdaftar = PaketLayanan::query()
                ->whereIn('id', $router->layanans()->where('status', '!=', StatusLayanan::Berhenti)->select('paket_layanan_id'))
                ->whereDoesntHave('routerPakets', fn ($query) => $query->where('router_id', $targetRouter->id))
                ->pluck('nama_paket');

            if ($paketBelumTerdaftar->isNotEmpty()) {
                Flux::toast(variant: 'danger', text: "Router tujuan {$targetRouter->nama_router} belum terdaftar untuk paket: {$paketBelumTerdaftar->implode(', ')}. Tambahkan router di Detail Paket terlebih dahulu.");

                return;
            }

            DB::transaction(function () use ($router, $targetRouter) {
                // Per-model (bukan update massal) agar observer berjalan: secret lama dibersihkan dan
                // layanan aktif langsung diprovisi ulang di router tujuan.
                LayananPelanggan::withTrashed()
                    ->where('router_id', $router->id)
                    ->get()
                    ->each(fn (LayananPelanggan $layanan) => $layanan->update(['router_id' => $targetRouter->id]));

                $router->routerPakets()->delete();
                $router->ipPools()->delete();
                $router->jobLogs()->delete();
                $router->delete();
            });

            $nama = $router->nama_router;
            $this->deletingId = null;
            $this->modal('confirm-delete-router')->close();
            Flux::toast(variant: 'success', text: "Router {$nama} berhasil dihapus dan layanannya dipindahkan ke {$targetRouter->nama_router}.");

            return;
        }

        DB::transaction(function () use ($router) {
            $router->routerPakets()->delete();
            $router->ipPools()->delete();
            $router->jobLogs()->delete();
            $router->delete();
        });

        $nama = $router->nama_router;
        $this->deletingId = null;
        $this->modal('confirm-delete-router')->close();
        Flux::toast(variant: 'success', text: "Router {$nama} berhasil dihapus.");
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
        $this->provisionRouterFull(Router::findOrFail($routerId), $mikrotikService);
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

        $routerToDelete = $this->deletingId
            ? Router::withCount(['ipPools', 'layanans'])->find($this->deletingId)
            : null;

        $otherRouters = $this->deletingId
            ? Router::where('id', '!=', $this->deletingId)->orderBy('nama_router')->get()
            : collect();

        return view('livewire.router.index', [
            'routers' => $routers,
            'statuses' => StatusRouter::cases(),
            'routerToDelete' => $routerToDelete,
            'otherRouters' => $otherRouters,
        ]);
    }
}
