<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Profil Bandwidth</flux:heading>
            <flux:subheading>Atur limit kecepatan upstream/downstream dan parameter burst MikroTik RouterOS.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="syncAllToRouters" wire:loading.attr="disabled" variant="subtle" icon="arrow-path">
                <span wire:loading.remove wire:target="syncAllToRouters">Sinkronisasi ke Router</span>
                <span wire:loading wire:target="syncAllToRouters">Menyinkronkan...</span>
            </flux:button>
            @can('create', App\Models\ProfilBandwidth::class)
                <flux:button :href="route('profil-bandwidth.create')" wire:navigate variant="primary" icon="plus">
                    Tambah Profil
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama profil bandwidth..."
            />
        </div>
    </div>

    {{-- Tabel Profil Bandwidth --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Profil</flux:table.column>
            <flux:table.column>Max Limit (TX / RX)</flux:table.column>
            <flux:table.column>Burst Rate</flux:table.column>
            <flux:table.column>Priority</flux:table.column>
            <flux:table.column>Paket Terkait</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($profils as $profil)
                <flux:table.row :key="$profil->id">
                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $profil->nama_bandwidth }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-1.5 font-mono text-sm">
                            <flux:badge size="sm" color="indigo">↑ {{ $profil->max_limit_tx }} Mbps</flux:badge>
                            <flux:badge size="sm" color="sky">↓ {{ $profil->max_limit_rx }} Mbps</flux:badge>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($profil->hasBurst())
                            <flux:badge size="sm" color="amber">
                                {{ $profil->burst_rate_tx }}M / {{ $profil->burst_rate_rx }}M ({{ $profil->burst_time_tx }}s)
                            </flux:badge>
                        @else
                            <span class="text-xs text-zinc-400">Nonaktif</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $profil->priority }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $profil->pakets_count }} paket</span>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $profil)
                                <flux:button
                                    :href="route('profil-bandwidth.edit', $profil)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Profil"
                                />
                            @endcan

                            @can('delete', $profil)
                                <flux:button
                                    wire:click="confirmDelete({{ $profil->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Profil"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="bolt" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada profil bandwidth ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($profils->hasPages())
        <div>
            {{ $profils->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Profil Bandwidth</flux:heading>
                <flux:subheading>
                    Apakah Anda yakin ingin menghapus profil bandwidth ini? Tindakan ini tidak dapat dibatalkan.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="deleteProfilBandwidth" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

