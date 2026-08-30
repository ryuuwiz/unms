<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Kelurahan</flux:heading>
            <flux:subheading>Kelola data kelurahan dan desa beserta kecamatan dan kota terkait.</flux:subheading>
        </div>
        @can('create', App\Models\Kota::class)
            <flux:button :href="route('wilayah.kelurahan.create')" wire:navigate variant="primary" icon="plus">
                Tambah Kelurahan
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama kelurahan..."
            />
        </div>

        <flux:select wire:model.live="filterKotaId" placeholder="Semua Kota" class="sm:w-52">
            <flux:select.option value="">Semua Kota</flux:select.option>
            @foreach ($kotas as $kota)
                <flux:select.option value="{{ $kota->id }}">{{ $kota->nama_kota }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterKecamatanId" placeholder="Semua Kecamatan" class="sm:w-52">
            <flux:select.option value="">Semua Kecamatan</flux:select.option>
            @foreach ($kecamatans as $kecamatan)
                <flux:select.option value="{{ $kecamatan->id }}">{{ $kecamatan->nama_kecamatan }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Kelurahan --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Kelurahan / Desa</flux:table.column>
            <flux:table.column>Kecamatan</flux:table.column>
            <flux:table.column>Kota / Kabupaten</flux:table.column>
            <flux:table.column>Perumahan</flux:table.column>
            <flux:table.column>Keterangan</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($kelurahans as $kelurahan)
                <flux:table.row :key="$kelurahan->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon name="map-pin" class="size-4 text-zinc-400" />
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $kelurahan->nama_kelurahan }}</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $kelurahan->kecamatan->nama_kecamatan ?? '-' }}</span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="text-zinc-500">{{ $kelurahan->kecamatan->kota->nama_kota ?? '-' }}</span>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $kelurahan->perumahans_count }} Perumahan
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500">
                        {{ $kelurahan->keterangan ?? '-' }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $kelurahan->kecamatan->kota)
                                <flux:button
                                    :href="route('wilayah.kelurahan.edit', $kelurahan)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Kelurahan"
                                />
                            @endcan

                            @can('delete', $kelurahan->kecamatan->kota)
                                <flux:button
                                    wire:click="confirmDelete({{ $kelurahan->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Kelurahan"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="map-pin" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada data kelurahan ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($kelurahans->hasPages())
        <div>
            {{ $kelurahans->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Kelurahan</flux:heading>
                <flux:subheading>
                    Apakah Anda yakin ingin menghapus data kelurahan ini? Tindakan ini tidak dapat dibatalkan jika tidak memiliki data turunan.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="deleteKelurahan" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
