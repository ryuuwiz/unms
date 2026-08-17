<?php

namespace App\Livewire\Routers;

use App\Models\Router;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
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

    public string $name = '';

    public string $ip_address = '';

    public string $username = '';

    public string $password = '';

    public string $description = '';

    public function mount(Router $router): void
    {
        $this->routerId = $router->id;
        $this->name = $router->name;
        $this->ip_address = $router->ip_address;
        $this->username = $router->username;
        // Do not mount password, leave it empty.
        $this->description = $router->description ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', "unique:routers,name,{$this->routerId}", 'regex:/^[A-Z0-9_]+$/'],
            'ip_address' => ['required', 'string', 'max:45', 'ip'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $router = Router::findOrFail($this->routerId);

        $updateData = [
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'username' => $this->username,
            'description' => $this->description ?: null,
        ];

        // Only update password if super admin provided a new one
        if (Auth::user()->hasRole('super_admin') && ! empty($this->password)) {
            $updateData['password'] = $this->password;
        }

        $router->update($updateData);

        Flux::toast(variant: 'success', text: 'Router berhasil diperbarui.');

        $this->redirectRoute('routers.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.routers.edit', [
            'router' => Router::findOrFail($this->routerId),
        ]);
    }
}
