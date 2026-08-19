<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Users</flux:heading>
            <flux:subheading>Kelola akun dan role staff internal.</flux:subheading>
        </div>
        <flux:button :href="route('users.create')" wire:navigate variant="primary" icon="plus">
            Tambah User
        </flux:button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama atau email..."
            />
        </div>
        <flux:select wire:model.live="filterRole" placeholder="Semua Role" class="sm:w-44">
            <flux:select.option value="">Semua Role</flux:select.option>
            @foreach ($roles as $role)
                <flux:select.option value="{{ $role->name }}">{{ Str::title(str_replace('_', ' ', $role->name)) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatus" placeholder="Semua Status" class="sm:w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Login Terakhir</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell class="font-medium">
                        <div class="flex items-center gap-2">
                            <flux:avatar size="sm" name="{{ $user->name }}" />
                            {{ $user->name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($role = $user->roles->first())
                            <flux:badge size="sm" color="blue">
                                {{ Str::title(str_replace('_', ' ', $role->name)) }}
                            </flux:badge>
                        @else
                            <span class="text-zinc-400 text-sm">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge
                            size="sm"
                            :color="$user->isActive() ? 'green' : 'zinc'"
                        >
                            {{ $user->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500 text-sm">
                        {{ $user->last_login_at?->diffForHumans() ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center justify-end gap-1">
                            @canImpersonate
                                @if ($user->canBeImpersonated())
                                    <flux:button
                                        :href="route('impersonate', $user->id)"
                                        size="sm"
                                        variant="subtle"
                                        icon="arrow-right-end-on-rectangle"
                                        title="Login sebagai {{ $user->name }}"
                                    >
                                        Impersonate
                                    </flux:button>
                                @endif
                            @endCanImpersonate
                            <flux:button
                                :href="route('users.edit', $user)"
                                wire:navigate
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                            >
                                Edit
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-400">
                        Tidak ada user yang ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $users->links() }}
    </div>
</div>
