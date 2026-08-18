<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Kecamatan</flux:heading>
            <flux:subheading>Kelola data kecamatan beserta kota/kabupaten induk.</flux:subheading>
        </div>
        @can('create', App\Models\Kota::class)
            <flux:button :href="route('wilayah.kecamatan.create')" wire:navigate variant="primary" icon="plus">
                Tambah Kecamatan
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama kecamatan..."
            />
        </div>

        <flux:select wire:model.live="filterKotaId" placeholder="Semua Kota" class="sm:w-56">
            <flux:select.option value="">Semua Kota</flux:select.option>
            @foreach ($kotas as $kota)
                <flux:select.option value="{{ $kota->id }}">{{ $kota->nama_kota }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Kecamatan --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Kecamatan</flux:table.column>
            <flux:table.column>Kota Induk</flux:table.column>
            <flux:table.column>Kelurahan</flux:table.column>
            <flux:table.column>Keterangan</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($kecamatans as $kecamatan)
                <flux:table.row :key="$kecamatan->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon name="map" class="size-4 text-zinc-400" />
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $kecamatan->nama_kecamatan }}</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                            <flux:icon name="building-office-2" class="size-3.5 text-zinc-400" />
                            <span>{{ $kecamatan->kota->nama_kota ?? '-' }}</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $kecamatan->kelurahans_count }} Kelurahan
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500">
                        {{ $kecamatan->keterangan ?? '-' }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $kecamatan->kota)
                                <flux:button
                                    :href="route('wilayah.kecamatan.edit', $kecamatan)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Kecamatan"
                                />
                            @endcan

                            @can('delete', $kecamatan->kota)
                                <flux:button
                                    wire:click="confirmDelete({{ $kecamatan->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Kecamatan"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="map" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada data kecamatan ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($kecamatans->hasPages())
        <div>
            {{ $kecamatans->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if ($deletingId)
        <flux:modal name="confirm-delete" :show="true" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Hapus Kecamatan</flux:heading>
                    <flux:subheading>
                        Apakah Anda yakin ingin menghapus data kecamatan ini? Tindakan ini akan diblokir jika kecamatan masih memiliki data kelurahan.
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                    <flux:button wire:click="deleteKecamatan" variant="danger">Hapus</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
