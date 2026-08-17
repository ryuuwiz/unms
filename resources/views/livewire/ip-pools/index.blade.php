<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">IP Pools</flux:heading>
            <flux:subheading>Kelola alokasi IP dan queue limit.</flux:subheading>
        </div>
        <flux:button :href="route('ip-pools.create')" wire:navigate variant="primary" icon="plus">
            Tambah IP Pool
        </flux:button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama pool atau network..."
            />
        </div>
        <flux:select wire:model.live="filterRouter" placeholder="Semua Router" class="sm:w-64">
            <flux:select.option value="">Semua Router</flux:select.option>
            @foreach ($routers as $router)
                <flux:select.option value="{{ $router->id }}">{{ $router->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Pool</flux:table.column>
            <flux:table.column>Router</flux:table.column>
            <flux:table.column>Network</flux:table.column>
            <flux:table.column>Queue Limit</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($pools as $pool)
                <flux:table.row :key="$pool->id">
                    <flux:table.cell class="font-medium">{{ $pool->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span>{{ $pool->router->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $pool->router->ip_address }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-mono text-sm">{{ $pool->ip_network }}/{{ $pool->cidr }}</span>
                            @if($pool->ip_range_start && $pool->ip_range_end)
                                <span class="text-xs text-zinc-500 font-mono">{{ $pool->ip_range_start }} - {{ $pool->ip_range_end }}</span>
                            @else
                                <span class="text-xs text-zinc-400 italic">Tanpa rentang spesifik</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="text-sm">TX: {{ (float) $pool->queue_tx_mbps }}M / RX: {{ (float) $pool->queue_rx_mbps }}M</span>
                            <span class="text-xs text-zinc-500">Pry: {{ $pool->priority_tx }}/{{ $pool->priority_rx }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button
                            :href="route('ip-pools.edit', $pool)"
                            wire:navigate
                            size="sm"
                            variant="ghost"
                            icon="pencil-square"
                        >
                            Edit
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center text-zinc-400">
                        Tidak ada IP Pool yang ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $pools->links() }}
    </div>
</div>
