<?php

namespace App\Livewire\Router;

use App\Models\Router;
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

    public function render(): View
    {
        return view('livewire.router.edit', [
            'router' => Router::findOrFail($this->routerId),
        ]);
    }
}
