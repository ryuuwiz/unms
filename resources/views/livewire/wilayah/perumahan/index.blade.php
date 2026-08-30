<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Perumahan & Cluster</flux:heading>
            <flux:subheading>Kelola cluster perumahan, kode singkatan, titik koordinat, dan relasi wilayah.</flux:subheading>
        </div>
        @can('create', App\Models\Kota::class)
            <flux:button :href="route('wilayah.perumahan.create')" wire:navigate variant="primary" icon="plus">
                Tambah Perumahan
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2 lg:col-span-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari perumahan / kode..."
            />
        </div>

        <flux:select wire:model.live="filterKotaId" placeholder="Semua Kota">
            <flux:select.option value="">Semua Kota</flux:select.option>
            @foreach ($kotas as $kota)
                <flux:select.option value="{{ $kota->id }}">{{ $kota->nama_kota }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterKecamatanId" placeholder="Semua Kecamatan" :disabled="!$filterKotaId && $kecamatans->isEmpty()">
            <flux:select.option value="">Semua Kecamatan</flux:select.option>
            @foreach ($kecamatans as $kecamatan)
                <flux:select.option value="{{ $kecamatan->id }}">{{ $kecamatan->nama_kecamatan }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterKelurahanId" placeholder="Semua Kelurahan" :disabled="!$filterKecamatanId && $kelurahans->isEmpty()">
            <flux:select.option value="">Semua Kelurahan</flux:select.option>
            @foreach ($kelurahans as $kelurahan)
                <flux:select.option value="{{ $kelurahan->id }}">{{ $kelurahan->nama_kelurahan }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabel Perumahan --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nama Perumahan / Cluster</flux:table.column>
            <flux:table.column>Kelurahan / Kecamatan</flux:table.column>
            <flux:table.column>Kota Induk</flux:table.column>
            <flux:table.column>Koordinat Peta</flux:table.column>
            <flux:table.column>Pelanggan / ODP</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($perumahans as $perumahan)
                <flux:table.row :key="$perumahan->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon name="home-modern" class="size-4 text-zinc-400" />
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $perumahan->nama_perumahan }}</span>
                                    @if ($perumahan->singkatan)
                                        <flux:badge size="xs" color="indigo" class="font-mono">
                                            {{ $perumahan->singkatan }}
                                        </flux:badge>
                                    @endif
                                </div>
                                @if ($perumahan->keterangan)
                                    <span class="text-xs text-zinc-500">{{ $perumahan->keterangan }}</span>
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col text-xs">
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $perumahan->kelurahan->nama_kelurahan ?? '-' }}
                            </span>
                            <span class="text-zinc-400">
                                Kec. {{ $perumahan->kelurahan->kecamatan->nama_kecamatan ?? '-' }}
                            </span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="text-zinc-600 dark:text-zinc-400">
                            {{ $perumahan->kelurahan->kecamatan->kota->nama_kota ?? '-' }}
                        </span>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($perumahan->latitude && $perumahan->longitude)
                            <button
                                type="button"
                                wire:click="showMap({{ $perumahan->id }})"
                                class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/70"
                                title="Buka Titik Peta"
                            >
                                <flux:icon name="map-pin" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span class="font-mono text-[11px]">{{ number_format($perumahan->latitude, 4) }}, {{ number_format($perumahan->longitude, 4) }}</span>
                            </button>
                        @else
                            <span class="text-xs text-zinc-400 italic">Belum diset</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="text-xs text-zinc-600 dark:text-zinc-300">
                            {{ $perumahan->pelanggans_count }} Pelanggan / {{ $perumahan->odps_count }} ODP
                        </span>
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $perumahan->kelurahan->kecamatan->kota)
                                <flux:button
                                    :href="route('wilayah.perumahan.edit', $perumahan)"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    title="Edit Perumahan"
                                />
                            @endcan

                            @can('delete', $perumahan->kelurahan->kecamatan->kota)
                                <flux:button
                                    wire:click="confirmDelete({{ $perumahan->id }})"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    title="Hapus Perumahan"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="home-modern" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada data perumahan ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($perumahans->hasPages())
        <div>
            {{ $perumahans->links() }}
        </div>
    @endif

    {{-- Modal View Map Leaflet --}}
    @if ($activeMapPerumahan && $activeMapPerumahan->latitude && $activeMapPerumahan->longitude)
        <flux:modal name="map-modal" :show="true" class="max-w-xl">
            <div class="space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ $activeMapPerumahan->nama_perumahan }}</flux:heading>
                        <flux:subheading>
                            {{ $activeMapPerumahan->kelurahan->nama_kelurahan }}, Kec. {{ $activeMapPerumahan->kelurahan->kecamatan->nama_kecamatan }}, {{ $activeMapPerumahan->kelurahan->kecamatan->kota->nama_kota }}
                        </flux:subheading>
                    </div>
                    <flux:button wire:click="closeMap" variant="ghost" size="sm" icon="x-mark" />
                </div>

                <x-map-view
                    :lat="$activeMapPerumahan->latitude"
                    :lng="$activeMapPerumahan->longitude"
                    height="320px"
                    :popup-title="$activeMapPerumahan->nama_perumahan"
                    :popup-subtitle="$activeMapPerumahan->singkatan ? '[' . $activeMapPerumahan->singkatan . ']' : null"
                />

                <div class="flex items-center justify-between text-xs text-zinc-500">
                    <span class="font-mono">Koordinat: {{ $activeMapPerumahan->latitude }}, {{ $activeMapPerumahan->longitude }}</span>
                    <flux:button wire:click="closeMap" variant="ghost" size="sm">Tutup</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Perumahan</flux:heading>
                <flux:subheading>
                    Apakah Anda yakin ingin menghapus data perumahan ini? Tindakan ini akan diblokir jika perumahan masih memiliki data pelanggan atau ODP terdaftar.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="deletePerumahan" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

