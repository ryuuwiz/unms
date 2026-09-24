<div class="space-y-6">
    <div>
        <flux:heading size="xl">Kategori & Kondisi Barang</flux:heading>
        <flux:subheading>Segmen kode unit barang: KATEGORI-KONDISI-BRAND-NOMOR (mis. MDM-NEW-BF-240). Brand diambil dari Prefix Registrasi.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach (['kategori' => ['Kategori Barang', $kategoris], 'kondisi' => ['Kondisi Barang', $kondisis]] as $master => [$judul, $baris])
            <flux:card class="space-y-4 p-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $judul }}</flux:heading>
                    <flux:button size="sm" icon="plus" wire:click="openModal('{{ $master }}')">Tambah</flux:button>
                </div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Kode</flux:table.column>
                        <flux:table.column>Nama</flux:table.column>
                        <flux:table.column align="end"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($baris as $item)
                            <flux:table.row :key="$master.'-'.$item->id">
                                <flux:table.cell><span class="font-mono font-semibold">{{ $item->kode }}</span></flux:table.cell>
                                <flux:table.cell>{{ $item->nama }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openModal('{{ $master }}', {{ $item->id }})" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="3" class="py-6 text-center text-zinc-500">Belum ada data.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endforeach
    </div>

    <flux:modal wire:model="showModal" class="max-w-md">
        <form wire:submit="simpan" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Ubah' : 'Tambah' }} {{ $master === 'kategori' ? 'Kategori' : 'Kondisi' }}</flux:heading>
            <flux:input wire:model="kode" label="Kode" maxlength="5" placeholder="{{ $master === 'kategori' ? 'MDM' : 'NEW' }}" />
            <flux:input wire:model="nama" label="Nama" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
