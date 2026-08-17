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

    public int $port = 8728;

    public string $username = 'admin';

    public string $password = '';

    public string $deskripsi = '';

    public function mount(): void
    {
        $this->authorize('create', Router::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_router' => ['required', 'string', 'max:100', 'unique:router,nama_router'],
            'ip_address' => ['required', 'string', 'max:45', 'ip'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
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
            'ip_address.required' => 'Alamat IP router wajib diisi.',
            'ip_address.ip' => 'Format IP address tidak valid.',
            'username.required' => 'Username API MikroTik wajib diisi.',
            'password.required' => 'Password API MikroTik wajib diisi.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Router::class);
        $this->validate();

        Router::create([
            'nama_router' => $this->nama_router,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'username' => $this->username,
            'password_terenkripsi' => $this->password,
            'deskripsi' => $this->deskripsi ?: null,
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
