<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data IP Pool</flux:heading>
            <flux:subheading>Kelola alokasi subnet IP network untuk pelanggan PPPoE dan IP Static.</flux:subheading>
        </div>
        @can('create', App\Models\IpPool::class)
            <flux:button :href="route('ip-pool.create')" wire:navigate variant="primary" icon="plus">
                Tambah IP Pool
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama pool atau IP network..."
            />
        </div>

        <flux:select wire:model.live="filterRouter" placeholder="Semua Router" class="sm:w-48">
            <flux:select.option value="">Semua Router</flux:select.option>
            @foreach ($routers as $router)
                <flux:select.option value="{{ $router->id }}">{{ $router->nama_router }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel IP Pool --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Pool</flux:table.column>
            <flux:table.column>Router</flux:table.column>
            <flux:table.column>Network (CIDR)</flux:table.column>
            <flux:table.column>Rentang IP</flux:table.column>
            <flux:table.column>Priority</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($pools as $pool)
                <flux:table.row :key="$pool->id">
                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $pool->nama_pool }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $pool->router->nama_router }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-sm">
                        {{ $pool->labelNetwork() }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs text-zinc-600 dark:text-zinc-300">
                        {{ $pool->rentang_ip_awal }} - {{ $pool->rentang_ip_akhir }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            <flux:badge size="sm" color="zinc">
                                {{ $pool->priority_tx }} / {{ $pool->priority_rx }}
                            </flux:badge>
                            @if ($pool->applied_to_router_at)
                                <span class="text-[11px] text-emerald-600 dark:text-emerald-400" title="Diterapkan: {{ $pool->applied_to_router_at->format('d/m/Y H:i') }}">
                                    ✓ Tersinkron
                                </span>
                            @elseif ($pool->sync_status === 'failed')
                                <span class="text-[11px] text-red-500" title="{{ $pool->last_sync_error }}">
                                    ✗ Gagal Sinkron
                                </span>
                            @else
                                <span class="text-[11px] text-zinc-400">
                                    Belum diterapkan
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $pool)
                                <flux:button
                                    wire:click="syncToRouter({{ $pool->id }})"
                                    wire:loading.attr="disabled"
                                    size="sm"
                                    variant="ghost"
                                    icon="arrow-path"
                                    class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                    title="Terapkan ke Router MikroTik"
                                />

                                <flux:button
                                    :href="route('ip-pool.edit', $pool)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit IP Pool"
                                />
                            @endcan

                            @can('delete', $pool)
                                <flux:button
                                    wire:click="confirmDelete({{ $pool->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus IP Pool"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="circle-stack" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada IP pool ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($pools->hasPages())
        <div>
            {{ $pools->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if ($deletingId)
        <flux:modal name="confirm-delete" :show="true" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Hapus IP Pool</flux:heading>
                    <flux:subheading>
                        Apakah Anda yakin ingin menghapus IP pool ini? Tindakan ini tidak dapat dibatalkan.
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                    <flux:button wire:click="deleteIpPool" variant="danger">Hapus</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
