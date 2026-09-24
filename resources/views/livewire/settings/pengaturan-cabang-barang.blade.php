<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Pengaturan Cabang Barang</flux:heading>
            <flux:subheading>Kelola daftar kode cabang (contoh: BF) yang menjadi segmen ketiga kode barang</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                Tambah Cabang
            </flux:button>
        </div>
    </div>

    <flux:card class="space-y-4 p-6">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama cabang..." icon="magnifying-glass" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Kode</flux:table.column>
                <flux:table.column>Nama</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>
                            <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $item->kode }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $item->nama }}</flux:table.cell>
                        <flux:table.cell>
                            @if($item->is_active)
                                <flux:badge color="emerald" size="sm" inset="top bottom">Aktif</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Nonaktif</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $item->id }})" title="Ubah" />
                                <flux:button variant="ghost" size="sm" :icon="$item->is_active ? 'pause' : 'play'" wire:click="toggleStatus({{ $item->id }})" :title="$item->is_active ? 'Nonaktifkan' : 'Aktifkan'" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">
                            Belum ada cabang. Klik tombol "Tambah Cabang" untuk menambahkan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showModal" class="max-w-md">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Cabang' : 'Tambah Cabang' }}</flux:heading>
                <flux:subheading>Kode dipakai sebagai segmen ketiga kode barang otomatis</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="kode" label="Kode Cabang" placeholder="Contoh: BF" maxlength="10" description="2-10 huruf/angka kapital, tanpa spasi" />
                <flux:input wire:model="nama" label="Nama / Keterangan" placeholder="Contoh: Batam" />
                <flux:field>
                    <flux:checkbox wire:model="is_active" label="Status Aktif" />
                    <flux:description>Cabang nonaktif tidak muncul di dropdown tambah barang.</flux:description>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    Simpan Cabang
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
