<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data IP Publik</flux:heading>
            <flux:subheading>Inventaris IP publik dedicated yang dapat dipasang ke layanan PPPoE sebagai add-on berbayar.</flux:subheading>
        </div>
        @can('create', App\Models\IpPublik::class)
            <flux:button :href="route('ip-publik.create')" wire:navigate variant="primary" icon="plus">
                Tambah IP Publik
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari alamat IP..." />
        </div>

        <flux:select wire:model.live="filterRouter" class="sm:w-48">
            <flux:select.option value="">Semua Router</flux:select.option>
            @foreach ($routers as $router)
                <flux:select.option value="{{ $router->id }}">{{ $router->nama_router }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterStatus" class="sm:w-40">
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="tersedia">Tersedia</flux:select.option>
            <flux:select.option value="terpakai">Terpakai</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Alamat IP</flux:table.column>
            <flux:table.column>Router</flux:table.column>
            <flux:table.column>Gateway</flux:table.column>
            <flux:table.column>Harga Bulanan</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell class="font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ $item->alamat_ip }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" color="zinc">{{ $item->router->nama_router }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="font-mono text-sm">{{ $item->gateway }}</flux:table.cell>
                    <flux:table.cell>Rp {{ number_format((float) $item->harga_bulanan, 0, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($item->isTersedia())
                            <flux:badge size="sm" color="green">Tersedia</flux:badge>
                        @else
                            <div class="flex flex-col gap-0.5">
                                <flux:badge size="sm" color="amber">Terpakai</flux:badge>
                                <span class="text-[11px] text-zinc-500">
                                    {{ $item->layananPelanggan?->site_id }} — {{ $item->layananPelanggan?->pelanggan?->nama_depan }} {{ $item->layananPelanggan?->pelanggan?->nama_belakang }}
                                </span>
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $item)
                                <flux:button :href="route('ip-publik.edit', $item)" wire:navigate size="sm" variant="ghost" icon="pencil-square" title="Edit IP Publik" />
                            @endcan
                            @can('delete', $item)
                                <flux:button wire:click="confirmDelete({{ $item->id }})" size="sm" variant="ghost" icon="trash" class="text-red-600 hover:text-red-700 dark:text-red-400" title="Hapus IP Publik" />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="globe-alt" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada IP Publik ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($items->hasPages())
        <div>{{ $items->links() }}</div>
    @endif

    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus IP Publik</flux:heading>
                <flux:subheading>
                    Hapus <strong>{{ $itemToDelete?->alamat_ip }}</strong> dari inventaris? Tindakan ini tidak dapat dibatalkan.
                </flux:subheading>
            </div>

            @if ($itemToDelete && ! $itemToDelete->isTersedia())
                <flux:callout variant="danger" icon="exclamation-triangle">
                    IP ini sedang dipakai layanan. Lepas dari layanan terlebih dahulu.
                </flux:callout>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deletingId', null)">Batal</flux:button>
                <flux:button wire:click="deleteIpPublik" variant="danger" :disabled="$itemToDelete && ! $itemToDelete->isTersedia()">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
