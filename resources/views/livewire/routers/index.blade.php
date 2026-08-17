<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Routers</flux:heading>
            <flux:subheading>Kelola data router.</flux:subheading>
        </div>
        <flux:button :href="route('routers.create')" wire:navigate variant="primary" icon="plus">
            Tambah Router
        </flux:button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Cari nama, IP, atau deskripsi..." />
        </div>
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
            <flux:table.column>Nama Router</flux:table.column>
            <flux:table.column>IP Address</flux:table.column>
            <flux:table.column>Deskripsi</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($routers as $router)
                <flux:table.row :key="$router->id">
                    <flux:table.cell class="font-medium">{{ $router->name }}</flux:table.cell>
                    <flux:table.cell>{{ $router->ip_address }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500 max-w-xs truncate" title="{{ $router->description }}">
                        {{ $router->description ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$router->status->color()">
                            {{ $router->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" inset="top bottom" />

                            <flux:menu>
                                <flux:menu.item :href="route('routers.edit', $router)" wire:navigate
                                    icon="pencil-square">
                                    Edit
                                </flux:menu.item>

                                <flux:menu.separator />

                                <flux:menu.item wire:click="toggleStatus({{ $router->id }})" icon="arrow-path">
                                    Tandai
                                    {{ $router->status === App\Enums\RouterStatus::Online ? 'Offline' : 'Online' }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center text-zinc-400">
                        Tidak ada router yang ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $routers->links() }}
    </div>
</div>
