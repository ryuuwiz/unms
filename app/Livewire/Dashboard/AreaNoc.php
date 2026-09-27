<?php

namespace App\Livewire\Dashboard;

use App\Enums\StatusPaket;
use App\Enums\StatusRouter;
use App\Models\IpPool;
use App\Models\PaketLayanan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Area Dashboard NOC & Infrastruktur: kondisi router, paket layanan, dan konfigurasi jaringan.
 */
#[Lazy]
class AreaNoc extends Component
{
    private const BARIS_ROUTER_OFFLINE = 5;

    public static function bolehLihat(User $user): bool
    {
        return $user->canAny(['router.lihat', 'paket_layanan.lihat', 'profil_bandwidth.lihat', 'ip_pool.lihat']);
    }

    /**
     * Ringkasan router: total, online, dan offline (termasuk yang belum pernah dicek), plus daftar yang offline.
     *
     * @return array{total: int, online: int, offline: int, daftar_offline: Collection<int, Router>}|null
     */
    public function getRouterProperty(): ?array
    {
        if (! auth()->user()->can('router.lihat')) {
            return null;
        }

        $total = Router::count();
        $online = Router::query()->online()->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $total - $online,
            'daftar_offline' => Router::query()
                ->where('status_koneksi', '!=', StatusRouter::Online)
                ->orderBy('nama_router')
                ->limit(self::BARIS_ROUTER_OFFLINE)
                ->get(['id', 'nama_router', 'ip_address', 'status_koneksi', 'last_ping_at']),
        ];
    }

    /**
     * Jumlah paket layanan (aktif/total), profil bandwidth, dan IP Pool; null per kunci bila tanpa izin.
     *
     * @return array{paket_aktif: int|null, paket_total: int|null, profil: int|null, ip_pool: int|null}
     */
    public function getKonfigurasiProperty(): array
    {
        $user = auth()->user();
        $bisaPaket = $user->can('paket_layanan.lihat');

        return [
            'paket_aktif' => $bisaPaket ? PaketLayanan::where('status', StatusPaket::Aktif)->count() : null,
            'paket_total' => $bisaPaket ? PaketLayanan::count() : null,
            'profil' => $user->can('profil_bandwidth.lihat') ? ProfilBandwidth::count() : null,
            'ip_pool' => $user->can('ip_pool.lihat') ? IpPool::count() : null,
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard.area-noc', [
            'router' => $this->getRouterProperty(),
            'konfigurasi' => $this->getKonfigurasiProperty(),
        ]);
    }
}
