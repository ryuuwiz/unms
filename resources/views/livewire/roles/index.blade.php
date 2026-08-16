<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Roles</flux:heading>
            <flux:subheading>Kelola role dan permissions yang dimiliki setiap role.</flux:subheading>
        </div>
        <flux:button :href="route('roles.create')" wire:navigate variant="primary" icon="plus">
            Tambah Role
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column>Users</flux:table.column>
            <flux:table.column>Permissions</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($roles as $role)
                <flux:table.row :key="$role->id">
                    <flux:table.cell class="font-medium">
                        {{ Str::title(str_replace('_', ' ', $role->name)) }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $role->users_count > 0 ? 'blue' : 'zinc' }}">
                            {{ $role->users_count }} user
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="violet">
                            {{ $role->permissions_count }} permission
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button
                                :href="route('roles.edit', $role)"
                                wire:navigate
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                            >
                                Edit
                            </flux:button>
                            <flux:button
                                wire:click="deleteRole({{ $role->id }})"
                                wire:confirm="Yakin ingin menghapus role '{{ $role->name }}'? Aksi ini tidak bisa dibatalkan."
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                class="text-red-500 hover:text-red-700"
                            >
                                Hapus
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-12 text-center text-zinc-400">
                        Tidak ada role yang ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
