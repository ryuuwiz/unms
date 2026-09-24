<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Daftar Barang</flux:heading>
            <flux:subheading>Kelola master data barang/inventaris, stok saat ini, dan kode barang otomatis.</flux:subheading>
        </div>
        @can('create', App\Models\Barang::class)
            <flux:button :href="route('barang.create')" wire:navigate variant="primary" icon="plus">
                Tambah Barang
            </flux:button>
        @endcan
    </div>

    {{-- Filter & Search Bar --}}
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex flex-col md:flex-row gap-4 md:items-end">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" label="Cari Nama / Kode Barang" placeholder="Cari nama atau kode barang..." />
        </div>
        <div class="w-full md:w-32">
            <flux:input type="number" wire:model.live.debounce.300ms="stokMin" label="Stok Min" placeholder="0" min="0" />
        </div>
        <div class="w-full md:w-32">
            <flux:input type="number" wire:model.live.debounce.300ms="stokMax" label="Stok Maks" placeholder="tanpa batas" min="0" />
        </div>
        <div>
            <flux:checkbox wire:model.live="stokMenipis" label="Stok Menipis (< {{ \App\Livewire\Barang\Index::AMBANG_STOK_MENIPIS }})" />
        </div>
    </div>

    {{-- Ekspor Data Barang (Stok Awal/Masuk/Keluar/Akhir per Periode) --}}
    @can('viewAny', App\Models\Barang::class)
        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex flex-col md:flex-row gap-4 md:items-end">
            <div class="w-full md:w-44">
                <flux:input type="date" wire:model="exportStartDate" label="Ekspor Dari Tanggal" />
            </div>
            <div class="w-full md:w-44">
                <flux:input type="date" wire:model="exportEndDate" label="Ekspor Sampai Tanggal" />
            </div>
            <flux:button wire:click="exportExcel" variant="filled" icon="arrow-down-tray">
                Ekspor Data Barang (Excel)
            </flux:button>
        </div>
    @endcan

    {{-- Tabel Barang --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Kode Barang</flux:table.column>
            <flux:table.column>Nama Barang</flux:table.column>
            <flux:table.column>Jenis / Kondisi / Cabang</flux:table.column>
            <flux:table.column>Stok</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($barangs as $barang)
                <flux:table.row :key="$barang->id">
                    <flux:table.cell>
                        <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $barang->kode_barang }}</span>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $barang->nama_barang }}</span>
                            @if ($barang->keterangan)
                                <span class="max-w-md truncate text-xs text-zinc-500" title="{{ $barang->keterangan }}">{{ $barang->keterangan }}</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap items-center gap-1">
                            <flux:badge size="sm" color="sky">{{ $barang->jenisBarang->kode }}</flux:badge>
                            <flux:badge size="sm" color="amber">{{ $barang->kondisiBarang->kode }}</flux:badge>
                            <flux:badge size="sm" color="zinc">{{ $barang->cabangBarang->kode }}</flux:badge>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <span class="font-semibold {{ $barang->stok < \App\Livewire\Barang\Index::AMBANG_STOK_MENIPIS ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                            {{ number_format($barang->stok) }}
                        </span>
                        <span class="text-xs text-zinc-500">{{ $barang->satuan }}</span>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($barang->is_active)
                            <flux:badge size="sm" color="emerald" inset="top bottom">Aktif</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc" inset="top bottom">Nonaktif</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            <flux:button :href="route('barang.label', $barang)" target="_blank" size="sm" variant="ghost" icon="qr-code" title="Cetak Barcode" />
                            @can('update', $barang)
                                <flux:button :href="route('barang.edit', $barang)" wire:navigate size="sm" variant="ghost" icon="pencil-square" title="Ubah Barang" />
                                <flux:button wire:click="toggleStatus({{ $barang->id }})" size="sm" variant="ghost" :icon="$barang->is_active ? 'pause' : 'play'" :title="$barang->is_active ? 'Nonaktifkan' : 'Aktifkan'" />
                            @endcan
                            @can('delete', $barang)
                                <flux:button wire:click="confirmDelete({{ $barang->id }})" size="sm" variant="ghost" icon="trash" class="text-red-600 hover:text-red-700 dark:text-red-400" title="Hapus Barang" />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="cube" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada barang ditemukan.</p>
                            @if ($search || $stokMin !== '' || $stokMax !== '' || $stokMenipis)
                                <p class="text-xs text-zinc-400">Coba ubah filter atau kata kunci pencarian.</p>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($barangs->hasPages())
        <div>{{ $barangs->links() }}</div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Hapus Barang</flux:heading>
                <flux:subheading>Apakah Anda yakin ingin menghapus barang ini? Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('deletingId', null)" variant="ghost">Batal</flux:button>
                <flux:button wire:click="deleteBarang" variant="danger">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
