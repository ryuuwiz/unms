<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data Barang</flux:heading>
            <flux:subheading>Rekap stok per bulan: stok awal, masuk, keluar, dan stok akhir dihitung dari mutasi barang.</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="arrow-down-tray" wire:click="exportExcel">Ekspor Excel</flux:button>
            @can('barang.buat')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Tambah Barang</flux:button>
            @endcan
        </div>
    </div>

    <flux:card class="space-y-4 p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input type="month" wire:model.live="bulan" label="Bulan" />
            <flux:input wire:model.live.debounce.300ms="search" label="Nama / Kode" placeholder="Cari barang..." icon="magnifying-glass" />
            <flux:select wire:model.live="kategoriId" label="Kategori">
                <flux:select.option value="">Semua Kategori</flux:select.option>
                @foreach($kategoris as $kategori)
                    <flux:select.option value="{{ $kategori->id }}">{{ $kategori->kode }} — {{ $kategori->nama }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="number" min="0" wire:model.live.debounce.300ms="stokMaks" label="Stok Akhir maks." placeholder="mis. 5 (0 = habis)" />
            <flux:select wire:model.live="urut" label="Urutkan">
                <flux:select.option value="nama">Nama Barang</flux:select.option>
                <flux:select.option value="stok_asc">Stok Akhir terkecil</flux:select.option>
                <flux:select.option value="stok_desc">Stok Akhir terbesar</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$barangs">
            <flux:table.columns>
                <flux:table.column>Kode Barang</flux:table.column>
                <flux:table.column>Nama Barang</flux:table.column>
                <flux:table.column align="end">Stok Awal</flux:table.column>
                <flux:table.column align="end">Masuk</flux:table.column>
                <flux:table.column align="end">Keluar</flux:table.column>
                <flux:table.column align="end">Stok Akhir</flux:table.column>
                <flux:table.column align="end">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($barangs as $barang)
                    <flux:table.row :key="$barang->id">
                        <flux:table.cell><span class="font-mono font-semibold">{{ $barang->kode }}</span></flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $barang->nama }}</div>
                            <div class="text-xs text-zinc-500">{{ $barang->kategori->nama }} · {{ $barang->satuan }}@if($barang->dilacak_per_unit) · dilacak per unit @endif</div>
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($barang->stok_awal, 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell align="end" class="text-emerald-600 dark:text-emerald-400">{{ number_format($barang->jumlah_masuk, 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell align="end" class="text-rose-600 dark:text-rose-400">{{ number_format($barang->jumlah_keluar, 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:badge size="sm" :color="$barang->stok_akhir <= 0 ? 'red' : 'zinc'">{{ number_format($barang->stok_akhir, 0, ',', '.') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                @if($barang->dilacak_per_unit)
                                    <flux:button variant="ghost" size="sm" icon="qr-code" :href="route('barang.unit', ['jenisId' => $barang->id])" wire:navigate title="Unit & label" />
                                @endif
                                @can('barang.ubah')
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $barang->id }})" title="Ubah" />
                                @endcan
                                @can('barang.hapus')
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="hapus({{ $barang->id }})" wire:confirm="Hapus data barang ini?" title="Hapus" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">Belum ada data barang yang sesuai filter.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showModal" class="max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Ubah Barang' : 'Tambah Barang' }}</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="kode" label="Kode Barang" placeholder="mis. MDM-ZTE-F609" />
                <flux:input wire:model="nama" label="Nama Barang" />
                <flux:select wire:model="formKategoriId" label="Kategori" placeholder="Pilih kategori...">
                    <flux:select.option value="">Pilih kategori...</flux:select.option>
                    @foreach($kategoris as $kategori)
                        <flux:select.option value="{{ $kategori->id }}">{{ $kategori->kode }} — {{ $kategori->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="satuan" label="Satuan" placeholder="pcs / meter / unit" />
                <flux:field>
                    <flux:checkbox wire:model="dilacakPerUnit" label="Dilacak per unit (setiap unit punya kode & barcode sendiri)" />
                    <flux:description>Untuk modem/ONT. Tidak dapat diubah setelah barang memiliki mutasi.</flux:description>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
