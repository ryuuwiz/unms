<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Kota</flux:heading>
            <flux:subheading>Kelola data kota dan kabupaten dalam cakupan operasional jaringan ISP.</flux:subheading>
        </div>
        @can('create', App\Models\Kota::class)
            <flux:button :href="route('wilayah.kota.create')" wire:navigate variant="primary" icon="plus">
                Tambah Kota
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama kota..."
            />
        </div>
    </div>

    {{-- Tabel Kota --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Kota / Kabupaten</flux:table.column>
            <flux:table.column>Kecamatan</flux:table.column>
            <flux:table.column>Kelurahan</flux:table.column>
            <flux:table.column>Keterangan</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($kotas as $kota)
                <flux:table.row :key="$kota->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon name="building-office-2" class="size-4 text-zinc-400" />
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $kota->nama_kota }}</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $kota->kecamatans_count }} Kecamatan
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $kota->kelurahans_count }} Kelurahan
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500">
                        {{ $kota->keterangan ?? '-' }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $kota)
                                <flux:button
                                    :href="route('wilayah.kota.edit', $kota)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Kota"
                                />
                            @endcan

                            @can('delete', $kota)
                                <flux:button
                                    wire:click="confirmDelete({{ $kota->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Kota"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="building-office-2" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada data kota ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($kotas->hasPages())
        <div>
            {{ $kotas->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if ($deletingId)
        <flux:modal name="confirm-delete" :show="true" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Hapus Kota</flux:heading>
                    <flux:subheading>
                        Apakah Anda yakin ingin menghapus data kota ini? Tindakan ini tidak dapat dibatalkan jika tidak memiliki data turunan.
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                    <flux:button wire:click="deleteKota" variant="danger">Hapus</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
