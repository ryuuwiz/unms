<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Router</flux:heading>
            <flux:subheading>Kelola MikroTik RouterOS gateway, IP API, port, dan status koneksi perangkat.</flux:subheading>
        </div>
        @can('create', App\Models\Router::class)
            <flux:button :href="route('router.create')" wire:navigate variant="primary" icon="plus">
                Tambah Router
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama router, IP address, atau deskripsi..."
            />
        </div>

        <flux:select wire:model.live="filterStatus" placeholder="Semua Status" class="sm:w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Router --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Router</flux:table.column>
            <flux:table.column>IP & Port API</flux:table.column>
            <flux:table.column>IP Pools / Layanan</flux:table.column>
            <flux:table.column>Status Koneksi</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($routers as $router)
                <flux:table.row :key="$router->id">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $router->nama_router }}</span>
                            @if ($router->deskripsi)
                                <span class="text-xs text-zinc-500">{{ $router->deskripsi }}</span>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-sm">
                        {{ $router->labelKoneksi() }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="text-xs text-zinc-600 dark:text-zinc-300">
                            {{ $router->ip_pools_count }} pool / {{ $router->layanans_count }} layanan
                        </span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            <flux:badge size="sm" :color="$router->status_koneksi->color()">
                                {{ $router->status_koneksi->label() }}
                            </flux:badge>
                            @if ($router->routeros_version || $router->cpu_load !== null)
                                <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500">
                                    @if ($router->routeros_version)
                                        <span>v{{ $router->routeros_version }}</span>
                                    @endif
                                    @if ($router->cpu_load !== null)
                                        <span>• CPU: {{ $router->cpu_load }}%</span>
                                    @endif
                                </div>
                            @elseif ($router->last_ping_at)
                                <span class="text-[11px] text-zinc-400">
                                    Ping: {{ $router->last_ping_at->diffForHumans() }}
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $router)
                                <flux:button
                                    wire:click="testConnection({{ $router->id }})"
                                    wire:loading.attr="disabled"
                                    size="sm"
                                    variant="ghost"
                                    icon="bolt"
                                    class="text-amber-600 hover:text-amber-700 dark:text-amber-400"
                                    title="Uji Koneksi RouterOS API"
                                />

                                <flux:button
                                    wire:click="provisionRouter({{ $router->id }})"
                                    wire:loading.attr="disabled"
                                    size="sm"
                                    variant="ghost"
                                    icon="arrow-path-rounded-square"
                                    class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                    title="Provisi Lengkap Router (IP Pool, Profil, PPP Secret)"
                                />

                                <flux:button
                                    :href="route('router.edit', $router)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Router"
                                />

                                <flux:button
                                    wire:click="toggleStatus({{ $router->id }})"
                                    size="sm"
                                    variant="ghost"
                                    :icon="$router->status_koneksi === App\Enums\StatusRouter::Online ? 'pause' : 'play'"
                                    :title="$router->status_koneksi === App\Enums\StatusRouter::Online ? 'Set Offline' : 'Set Online'"
                                />
                            @endcan

                            @can('delete', $router)
                                <flux:button
                                    wire:click="confirmDelete({{ $router->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Router"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="server" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada router ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($routers->hasPages())
        <div>
            {{ $routers->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal name="confirm-delete" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Router</flux:heading>
                <flux:subheading>
                    Apakah Anda yakin ingin menghapus router ini? Tindakan ini permanen.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="$set('deletingId', null)">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deleteRouter" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
