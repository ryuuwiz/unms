<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Paket Layanan</flux:heading>
            <flux:subheading>Kelola katalog layanan internet, profil bandwidth, masa aktif, dan tarif.</flux:subheading>
        </div>
        @can('create', App\Models\PaketLayanan::class)
            <flux:button :href="route('paket-layanan.create')" wire:navigate variant="primary" icon="plus">
                Tambah Paket
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama paket atau keterangan..."
            />
        </div>

        <flux:select wire:model.live="filterStatus" placeholder="Semua Status" class="sm:w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            @foreach ($statuses as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Paket --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Paket</flux:table.column>
            <flux:table.column>Profil Bandwidth</flux:table.column>
            <flux:table.column>Tarif</flux:table.column>
            <flux:table.column>Masa Aktif</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($pakets as $paket)
                <flux:table.row :key="$paket->id">
                    {{-- Nama Paket & Keterangan --}}
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $paket->nama_paket }}</span>
                            @if ($paket->keterangan)
                                <span class="max-w-md truncate text-xs text-zinc-500" title="{{ $paket->keterangan }}">
                                    {{ $paket->keterangan }}
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>

                    {{-- Profil Bandwidth --}}
                    <flux:table.cell>
                        <div class="flex items-center gap-1.5">
                            <flux:badge size="sm" color="sky">
                                {{ $paket->profilBandwidth?->nama_bandwidth ?? '—' }}
                            </flux:badge>
                            @if ($paket->profilBandwidth)
                                <span class="text-xs text-zinc-500">({{ $paket->profilBandwidth->labelKecepatan() }})</span>
                            @endif
                        </div>
                    </flux:table.cell>

                    {{-- Tarif --}}
                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $paket->formattedHarga() }}
                    </flux:table.cell>

                    {{-- Masa Aktif --}}
                    <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $paket->labelMasaAktif() }}
                    </flux:table.cell>

                    {{-- Status --}}
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$paket->status->color()">
                            {{ $paket->status->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    {{-- Aksi --}}
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $paket)
                                <flux:button
                                    :href="route('paket-layanan.edit', $paket)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Paket"
                                />

                                <flux:button
                                    wire:click="toggleStatus({{ $paket->id }})"
                                    size="sm"
                                    variant="ghost"
                                    :icon="$paket->status === App\Enums\StatusPaket::Aktif ? 'pause' : 'play'"
                                    :title="$paket->status === App\Enums\StatusPaket::Aktif ? 'Nonaktifkan Paket' : 'Aktifkan Paket'"
                                />
                            @endcan

                            @can('delete', $paket)
                                <flux:button
                                    wire:click="confirmDelete({{ $paket->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Paket"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="queue-list" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada paket layanan ditemukan.</p>
                            @if ($search || $filterStatus)
                                <p class="text-xs text-zinc-400">Coba ubah filter atau kata kunci pencarian.</p>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($pakets->hasPages())
        <div>
            {{ $pakets->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if ($deletingId)
        <flux:modal name="confirm-delete" :show="true" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Hapus Paket Layanan</flux:heading>
                    <flux:subheading>
                        Apakah Anda yakin ingin menghapus paket layanan ini? Tindakan ini tidak dapat dibatalkan.
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                    <flux:button wire:click="deletePaket" variant="danger">Hapus</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
