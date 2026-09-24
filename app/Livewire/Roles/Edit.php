<?php

namespace App\Livewire\Roles;

use Flux\Flux;
use Illuminate\Support\Collection;
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
        $this->roleId = $role->getKey();
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', "unique:roles,name,{$this->roleId}"],
        ]);

        $role = Role::findOrFail($this->roleId);

        $role->update(['name' => Str::lower($this->name)]);
        $role->syncPermissions($this->selectedPermissions);

        Flux::toast(variant: 'success', text: "Role \"{$role->name}\" berhasil diperbarui.");

        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.roles.edit', [
            'groupedPermissions' => $this->getGroupedPermissions(),
        ]);
    }

    /**
     * Group all available permissions by module.
     *
     * @return array<string, Collection<int, Permission>>
     */
    private function getGroupedPermissions(): array
    {
        return Permission::all()
            ->groupBy(function (Permission $permission) {
                return explode('.', $permission->name)[0];
            })
            ->all();
    }
}
