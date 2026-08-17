<div class="mx-auto max-w-lg space-y-6">
    <div>
        <flux:heading size="xl">Tambah Role</flux:heading>
        <flux:subheading>Buat role baru dan tentukan permissions-nya.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">

        <flux:field>
            <flux:label>Nama Role</flux:label>
            <flux:input
                wire:model="name"
                placeholder="contoh: supervisor"
                description="Gunakan huruf kecil dan underscore. Contoh: sales_manager"
                autofocus
            />
            <flux:error name="name" />
        </flux:field>

        <div class="space-y-4">
            <flux:heading size="sm">Permissions</flux:heading>

            @foreach ($groupedPermissions as $module => $permissions)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="mb-3 font-semibold capitalize">
                        {{ Str::headline($module) }}
                    </flux:text>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            @php
                                $permName = is_string($permission) ? $permission : ($permission->name ?? $permission['name'] ?? '');
                                $action = Str::contains($permName, '.') ? Str::afterLast($permName, '.') : $permName;
                                $label = Str::headline($action);
                            @endphp
                            <flux:checkbox
                                wire:model="selectedPermissions"
                                :value="$permName"
                                :label="$label"
                            />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-3 pt-2">
            <flux:spacer />
            <flux:button :href="route('roles.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary">Simpan Role</flux:button>
        </div>

    </form>
</div>
