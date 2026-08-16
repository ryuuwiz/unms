<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Create User')]
class Create extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('nullable|string|max:20')]
    public string $phone = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    #[Validate('required|string|exists:roles,name')]
    public string $role = '';

    public function save(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'password' => $this->password,
            'status' => UserStatus::Active,
        ]);

        $user->assignRole($this->role);

        Flux::toast(variant: 'success', text: 'User berhasil dibuat.');

        $this->redirectRoute('users.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.users.create', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
