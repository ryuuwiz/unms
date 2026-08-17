<?php

namespace App\Livewire\Routers;

use App\Enums\RouterStatus;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Router')]
class Create extends Component
{
    #[Validate(['required', 'string', 'max:255', 'unique:routers,name', 'regex:/^[A-Z0-9_]+$/'])]
    public string $name = '';

    #[Validate(['required', 'string', 'max:45', 'ip'])]
    public string $ip_address = '';

    #[Validate(['required', 'string', 'max:255'])]
    public string $username = '';

    #[Validate(['nullable', 'string', 'max:255'])]
    public string $password = '';

    #[Validate(['nullable', 'string', 'max:1000'])]
    public string $description = '';

    public function save(): void
    {
        $this->validate();

        Router::create([
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'username' => $this->username,
            'password' => $this->password ?: null,
            'description' => $this->description ?: null,
            'status' => RouterStatus::Unknown,
        ]);

        Flux::toast(variant: 'success', text: 'Router berhasil dibuat.');

        $this->redirectRoute('routers.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.routers.create');
    }
}
