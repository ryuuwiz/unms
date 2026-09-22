<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Pengaturan Prefix Registrasi</flux:heading>
            <flux:subheading>Kelola daftar prefix No. Registrasi pelanggan (contoh: BF, ARS) yang dapat dipilih saat pendaftaran pelanggan baru</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                Tambah Prefix
            </flux:button>
        </div>
    </div>

    {{-- Main Card & Table --}}
    <flux:card class="space-y-4 p-6">
        <div class="w-full sm:w-72">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari kode atau nama prefix..."
                icon="magnifying-glass"
            />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Kode</flux:table.column>
                <flux:table.column>Nama</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($prefixes as $prefix)
                    <flux:table.row :key="$prefix->id">
                        <flux:table.cell>
                            <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $prefix->kode }}</span>
                        </flux:table.cell>

                        <flux:table.cell>{{ $prefix->nama }}</flux:table.cell>

                        <flux:table.cell>
                            @if($prefix->is_active)
                                <flux:badge color="emerald" size="sm" inset="top bottom">Aktif</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Nonaktif</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil-square"
                                    wire:click="openEditModal({{ $prefix->id }})"
                                    title="Ubah"
                                />

                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    :icon="$prefix->is_active ? 'pause' : 'play'"
                                    wire:click="toggleStatus({{ $prefix->id }})"
                                    :title="$prefix->is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">
                            Belum ada prefix registrasi. Klik tombol "Tambah Prefix" untuk menambahkan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal Form Create / Edit Prefix --}}
    <flux:modal wire:model="showModal" class="max-w-md">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Prefix Registrasi' : 'Tambah Prefix Registrasi' }}</flux:heading>
                <flux:subheading>Kode dipakai sebagai awalan No. Registrasi pelanggan baru</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input
                    wire:model="kode"
                    label="Kode Prefix"
                    placeholder="Contoh: BF"
                    maxlength="5"
                    description="2-5 huruf kapital, tanpa spasi"
                />

                <flux:input
                    wire:model="nama"
                    label="Nama / Keterangan"
                    placeholder="Contoh: Bestfiber"
                />

                <flux:field>
                    <flux:checkbox wire:model="is_active" label="Status Aktif" />
                    <flux:description>Prefix nonaktif tidak muncul di dropdown pendaftaran pelanggan.</flux:description>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    Simpan Prefix
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
