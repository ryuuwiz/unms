<?php

namespace App\Livewire\Roles;

use App\Models\User;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Edit Role')]
class Edit extends Component
{
    #[Locked]
    public int $roleId;

    public string $name = '';

    /** @var array<string> */
    public array $selectedPermissions = [];

    public function mount(Role $role): void
    {
        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', "unique:roles,name,{$this->roleId}"],
        ]);

        $role = Role::findOrFail($this->roleId);

        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();

        $role->update(['name' => Str::lower($this->name)]);
        $role->syncPermissions($this->selectedPermissions);

        $newPermissions = collect($this->selectedPermissions)->sort()->values()->all();

        if ($oldPermissions !== $newPermissions) {
            /** @var User $actor */
            $actor = Auth::user();
            AuditLogger::recordRolePermissionsUpdated($actor, $role, $oldPermissions, $newPermissions);
        }

        Flux::toast(variant: 'success', text: "Role \"{$role->name}\" berhasil diperbarui.");

        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.roles.edit', [
            'groupedPermissions' => $this->getGroupedPermissions(),
            'role' => Role::findOrFail($this->roleId),
        ]);
    }

    /**
     * Group all available permissions by module (derived from name prefix).
     *
     * @return array<string, Collection<int, Permission>>
     */
    private function getGroupedPermissions(): array
    {
        return Permission::orderBy('name')
            ->get()
            ->groupBy(fn ($perm) => Str::after(
                $perm->name,
                Str::startsWith($perm->name, 'manage_') ? 'manage_' : 'view_'
            ))
            ->toArray();
    }
}
