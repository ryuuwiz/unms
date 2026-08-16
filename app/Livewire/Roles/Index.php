<?php

namespace App\Livewire\Roles;

use App\Models\User;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Roles')]
class Index extends Component
{
    public function deleteRole(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        $userCount = User::whereHas('roles', fn ($q) => $q->where('id', $roleId))->count();

        if ($userCount > 0) {
            Flux::toast(
                variant: 'danger',
                text: "Role \"{$role->name}\" tidak dapat dihapus karena masih digunakan oleh {$userCount} user."
            );

            return;
        }

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordRoleDeleted($actor, $role);

        $role->delete();

        Flux::toast(variant: 'success', text: "Role \"{$role->name}\" berhasil dihapus.");
    }

    public function render(): View
    {
        $roles = Role::withCount('users', 'permissions')
            ->orderBy('name')
            ->get();

        return view('livewire.roles.index', [
            'roles' => $roles,
        ]);
    }
}
