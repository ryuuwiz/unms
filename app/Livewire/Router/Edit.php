<?php

namespace App\Livewire\Router;

use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Router')]
class Edit extends Component
{
    #[Locked]
    public int $routerId;

    public string $nama_router = '';

    public string $ip_address = '';

    public int $port = 8728;

    public string $username = '';

    public string $password = '';

    public string $deskripsi = '';

    public function mount(Router $router): void
    {
        $this->authorize('update', $router);

        $this->routerId = $router->id;
        $this->nama_router = $router->nama_router;
        $this->ip_address = $router->ip_address;
        $this->port = $router->port;
        $this->username = $router->username;
        $this->deskripsi = $router->deskripsi ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_router' => ['required', 'string', 'max:100', "unique:router,nama_router,{$this->routerId}"],
            'ip_address' => ['required', 'string', 'max:45', 'ip'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function save(): void
    {
        $router = Router::findOrFail($this->routerId);
        $this->authorize('update', $router);
        $this->validate();

        $updateData = [
            'nama_router' => $this->nama_router,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'username' => $this->username,
            'deskripsi' => $this->deskripsi ?: null,
        ];

        if (! empty($this->password)) {
            $updateData['password_terenkripsi'] = $this->password;
        }

        $router->update($updateData);

        Flux::toast(variant: 'success', text: 'Data router berhasil diperbarui.');

        $this->redirectRoute('router.index', navigate: true);
    }

    public function testConnection(MikrotikService $mikrotikService): void
    {
        $router = Router::findOrFail($this->routerId);
        $this->authorize('update', $router);

        try {
            $mikrotikService->testConnection($router, 4);
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

    public function autoRecoverPpp(MikrotikService $mikrotikService): void
    {
        $router = Router::findOrFail($this->routerId);
        $this->authorize('update', $router);

        try {
            $stats = $mikrotikService->autoRecoverPppSecrets($router);

            MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::ReconcilePppoe,
                'status' => MikrotikJobStatus::Success,
                'attempt_count' => 1,
                'payload' => $stats,
                'finished_at' => now(),
            ]);

            if (($stats['recovered'] ?? 0) > 0) {
                Flux::toast(
                    variant: 'success',
                    text: "Auto-Recovery berhasil: {$stats['recovered']} akun dipulihkan/disinkronkan, {$stats['already_synced']} sudah sesuai."
                );
            } else {
                Flux::toast(
                    variant: 'info',
                    text: "Seluruh {$stats['total_checked']} akun PPPoE di router sudah lengkap dan sinkron."
                );
            }
        } catch (\Throwable $e) {
            MikrotikJobLog::create([
                'router_id' => $router->id,
                'job_type' => MikrotikJobType::ReconcilePppoe,
                'status' => MikrotikJobStatus::Failed,
                'attempt_count' => 1,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Flux::toast(
                variant: 'danger',
                text: "Gagal auto-recover PPP: {$e->getMessage()}"
            );
        }
    }

    public function render(): View
    {
        $router = Router::with(['jobLogs' => fn ($q) => $q->latest()->limit(5)])
            ->findOrFail($this->routerId);

        return view('livewire.router.edit', [
            'router' => $router,
        ]);
    }
}
