<?php

namespace App\Livewire\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Users')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterRole = '';

    public string $filterStatus = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $users = User::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->filterRole, fn ($q) => $q->whereHas(
                'roles',
                fn ($q) => $q->where('name', $this->filterRole)
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->with('roles')
            ->latest()
            ->paginate(15);

        $roles = Role::orderBy('name')->get();

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => $roles,
            'statuses' => UserStatus::cases(),
        ]);
    }
}
