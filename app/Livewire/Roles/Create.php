<?php

namespace App\Livewire\Roles;

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Tambah Role')]
class Create extends Component
{
    #[Validate('required|string|max:50|regex:/^[a-z0-9_]+$/|unique:roles,name')]
    public string $name = '';

    /** @var array<string> */
    public array $selectedPermissions = [];

    public function save(): void
    {
        $this->validate();

        $role = Role::create(['name' => Str::lower($this->name)]);

        if (! empty($this->selectedPermissions)) {
            $role->syncPermissions($this->selectedPermissions);
        }

        Flux::toast(variant: 'success', text: "Role \"{$role->name}\" berhasil dibuat.");

        $this->redirectRoute('roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.roles.create', [
            'groupedPermissions' => $this->getGroupedPermissions(),
        ]);
    }

    /**
     * Group all available permissions by module (derived from name prefix).
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
