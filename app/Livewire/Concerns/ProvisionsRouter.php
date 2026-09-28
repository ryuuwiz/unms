<?php

namespace App\Livewire\Concerns;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;

trait ProvisionsRouter
{
    public function provisionRouterFull(Router $router, MikrotikService $mikrotikService): void
    {
        $this->authorize('update', $router);

        try {
            $result = $mikrotikService->provisionRouterFull($router);
            $details = $result['details'] ?? [];
            $poolSynced = $details['ip_pools']['synced'] ?? 0;
            $profileSynced = $details['profiles']['synced'] ?? 0;
            $secretRecovered = $details['secrets']['recovered'] ?? 0;
            $orphansFound = $details['orphans']['orphans_count'] ?? 0;

            // Orphaned secret tidak pernah dihapus sistem, hanya dilaporkan (ADR-0063).
            $orphanText = $orphansFound > 0 ? ", Orphan terdeteksi: {$orphansFound} (tidak dihapus)" : '';

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
}
