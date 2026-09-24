<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Template Deskripsi Tagihan Gateway</flux:heading>
            <flux:subheading>Teks deskripsi invoice periodik yang tampil di halaman checkout payment gateway. Berlaku untuk link bayar baru.</flux:subheading>
        </div>
        @can('payment_gateway.buat')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Tambah Template</flux:button>
        @endcan
    </div>

    <flux:card class="space-y-4 p-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama</flux:table.column>
                <flux:table.column>Isi Template</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($templates as $template)
                    <flux:table.row :key="$template->id">
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $template->nama }}</flux:table.cell>
                        <flux:table.cell><span class="font-mono text-xs">{{ $template->konten }}</span></flux:table.cell>
                        <flux:table.cell>
                            @if($template->is_default)
                                <flux:badge color="emerald" size="sm" inset="top bottom">Default</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('payment_gateway.ubah')
                                    @unless($template->is_default)
                                        <flux:button variant="ghost" size="sm" icon="check-circle" wire:click="jadikanDefault({{ $template->id }})" title="Jadikan default" />
                                    @endunless
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $template->id }})" title="Ubah" />
                                @endcan
                                @can('payment_gateway.hapus')
                                    @unless($template->is_default)
                                        <flux:button variant="ghost" size="sm" icon="trash" wire:click="hapus({{ $template->id }})" wire:confirm="Hapus template ini?" title="Hapus" />
                                    @endunless
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">Belum ada template.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showModal" class="max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Template' : 'Tambah Template' }}</flux:heading>
                <flux:subheading>Maksimal 255 karakter akan dikirim ke gateway.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="nama" label="Nama Template" placeholder="Contoh: Default Pembayaran Internet" />

                <flux:textarea wire:model="konten" label="Isi Template" rows="3" />

                <div class="text-xs text-zinc-500">
                    Placeholder:
                    @foreach($placeholders as $placeholder)
                        <code class="mr-1 rounded bg-zinc-100 px-1 dark:bg-zinc-800">{{ '{'.$placeholder.'}' }}</code>
                    @endforeach
                </div>

                <flux:checkbox wire:model="is_default" label="Jadikan template default" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Template</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
