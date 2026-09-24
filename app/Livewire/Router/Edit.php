<?php

namespace App\Livewire\Router;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Models\MikrotikJobLog;
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

    public ?int $port = 8728;

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
     * @return array{host: string, port: ?int}
     */
    protected function parseHostAndPort(string $raw): array
    {
        $cleaned = trim($raw);
        $cleaned = (string) preg_replace('#^https?://#i', '', $cleaned);
        $cleaned = rtrim($cleaned, '/');

        if (preg_match('/^([a-zA-Z0-9\.\-_]+):(\d+)$/', $cleaned, $matches)) {
            return [
                'host' => $matches[1],
                'port' => (int) $matches[2],
            ];
        }

        return [
            'host' => $cleaned,
            'port' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_router' => ['required', 'string', 'max:100', "unique:router,nama_router,{$this->routerId}"],
            'ip_address' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $parsed = $this->parseHostAndPort((string) $value);
                    $host = $parsed['host'];
                    $isValidIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
                    $isValidDomain = filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
                    if (! $isValidIp && ! $isValidDomain) {
                        $fail('Format IP address atau hostname router tidak valid.');
                    }
                },
            ],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nama_router.required' => 'Nama router wajib diisi.',
            'nama_router.unique' => 'Nama router sudah digunakan.',
            'ip_address.required' => 'Alamat IP atau hostname router wajib diisi.',
            'username.required' => 'Username API MikroTik wajib diisi.',
            'port.integer' => 'Port API harus berupa angka.',
            'port.min' => 'Port API minimal 1.',
            'port.max' => 'Port API maksimal 65535.',
        ];
    }

    public function save(): void
    {
        $router = Router::findOrFail($this->routerId);
        $this->authorize('update', $router);
        $this->validate();

        $parsed = $this->parseHostAndPort($this->ip_address);
        $port = $parsed['port'] ?? ($this->port ?: 8728);

        $updateData = [
            'nama_router' => trim($this->nama_router),
            'ip_address' => $parsed['host'],
            'port' => $port,
            'username' => trim($this->username),
            'deskripsi' => $this->deskripsi ? trim($this->deskripsi) : null,
        ];

        if ($this->password !== '') {
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

            $profileStats = $stats['profiles'];
            $profileSynced = $profileStats['synced'];
            $profileTotal = $profileStats['total'];

            if ($stats['recovered'] > 0) {
                Flux::toast(
                    variant: 'success',
                    text: "Auto-Recovery berhasil: {$profileSynced}/{$profileTotal} profil disinkronkan, {$stats['recovered']} akun dipulihkan/disinkronkan, {$stats['already_synced']} sudah sesuai."
                );
            } else {
                Flux::toast(
                    variant: 'info',
                    text: "Auto-Recovery selesai: {$profileSynced}/{$profileTotal} profil & seluruh {$stats['total_checked']} akun PPPoE di router sudah lengkap dan sinkron."
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

    public function provisionFullRouter(MikrotikService $mikrotikService): void
    {
        $router = Router::findOrFail($this->routerId);
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
                text: "Provisi penuh {$router->nama_router} sukses! (Pool: {$poolSynced}, Profil: {$profileSynced}, Secret: {$secretRecovered}{$orphanText})"
            );
        } catch (\Throwable $e) {
            Flux::toast(
                variant: 'danger',
                text: "Gagal provisi penuh: {$e->getMessage()}"
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
