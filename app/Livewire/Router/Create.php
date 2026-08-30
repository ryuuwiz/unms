<?php

namespace App\Livewire\Router;

use App\Enums\StatusRouter;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Router')]
class Create extends Component
{
    public string $nama_router = '';

    public string $ip_address = '';

    public ?int $port = 8728;

    public string $username = 'admin';

    public string $password = '';

    public string $deskripsi = '';

    public function mount(): void
    {
        $this->authorize('create', Router::class);
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
            'nama_router' => ['required', 'string', 'max:100', 'unique:router,nama_router'],
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
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
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
            'port.required' => 'Port API wajib diisi.',
            'port.integer' => 'Port API harus berupa angka.',
            'port.min' => 'Port API minimal 1.',
            'port.max' => 'Port API maksimal 65535.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Router::class);
        $this->validate();

        $parsed = $this->parseHostAndPort($this->ip_address);
        $port = $parsed['port'] ?? $this->port ?? 8728;

        Router::create([
            'nama_router' => trim($this->nama_router),
            'ip_address' => $parsed['host'],
            'port' => $port,
            'username' => trim($this->username),
            'password_terenkripsi' => $this->password ?? '',
            'deskripsi' => $this->deskripsi ? trim($this->deskripsi) : null,
            'status_koneksi' => StatusRouter::Unknown,
        ]);

        Flux::toast(variant: 'success', text: 'Router berhasil ditambahkan.');

        $this->redirectRoute('router.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.router.create');
    }
}
