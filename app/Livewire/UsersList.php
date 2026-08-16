<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class UsersList extends Component
{
    use WithPagination;

    public $search = '';
    public $userToDelete = null;
    
    // Form state
    public $showModal = false;
    public $name = '';
    public $email = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function save()
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

    public function confirmDelete($userId)
    {
        $this->userToDelete = $userId;
        $this->dispatch('open-modal', 'delete-user-modal');
    }

    public function deleteUser()
    {
        if ($this->userToDelete) {
            User::find($this->userToDelete)?->delete();
            \Flux::toast('User deleted successfully.');
        }
        
        $this->dispatch('close-modal', 'delete-user-modal');
        $this->userToDelete = null;
    }

    public function render()
    {
        return view('livewire.users-list', [
            'users' => User::where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->paginate(5)
        ]);
    }
}
