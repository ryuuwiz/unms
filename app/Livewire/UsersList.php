<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UsersList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $userToDelete = null;

    // Form state
    public bool $showModal = false;

    public string $name = '';

    public string $email = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt('password'),
        ]);

        $this->showModal = false;
        $this->reset(['name', 'email']);

        \Flux::toast('User created successfully.');
    }

    public function confirmDelete(int $userId): void
    {
        $this->userToDelete = $userId;
        $this->dispatch('open-modal', 'delete-user-modal');
    }

    public function deleteUser(): void
    {
        if ($this->userToDelete) {
            User::find($this->userToDelete)?->delete();
            \Flux::toast('User deleted successfully.');
        }

        $this->dispatch('close-modal', 'delete-user-modal');
        $this->userToDelete = null;
    }

    public function render(): View
    {
        return view('livewire.users-list', [
            'users' => User::where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
                ->paginate(5),
        ]);
    }
}
