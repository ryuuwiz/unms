<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">RAB Kantor</flux:heading>
            <flux:subheading>Rancangan anggaran belanja kantor per bulan.</flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="table-cells" wire:click="exportExcel">Excel</flux:button>
            <flux:button icon="printer" :href="route('rab.cetak', ['bulan' => $bulan])" target="_blank">PDF</flux:button>
            @if ($bolehUbah)
                @if ($items->isEmpty())
                    <flux:button icon="document-duplicate" wire:click="salinBulanLalu" wire:confirm="Salin semua item dari bulan sebelumnya?">Salin Bulan Lalu</flux:button>
                @endif
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Tambah Item</flux:button>
            @endif
        </div>
    </div>

    <flux:card class="space-y-4 p-6">
        <div class="flex flex-wrap items-end gap-4">
            <flux:input type="month" wire:model.live="bulan" label="Bulan" class="max-w-xs" />
            @if ($terkunci)
                <flux:badge color="zinc" icon="lock-closed">Terkunci</flux:badge>
            @endif
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>No</flux:table.column>
                <flux:table.column>Uraian</flux:table.column>
                <flux:table.column align="end">Qty</flux:table.column>
                <flux:table.column align="end">Harga</flux:table.column>
                <flux:table.column align="end">Jumlah</flux:table.column>
                <flux:table.column>Divisi</flux:table.column>
                @if ($bolehUbah)
                    <flux:table.column></flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell>{{ $loop->iteration }}</flux:table.cell>
                        <flux:table.cell>{{ $item->uraian }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($item->qty, 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell align="end">Rp {{ number_format($item->harga, 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell align="end">Rp {{ number_format($item->jumlah(), 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($item->divisi)
                                <flux:badge size="sm" :color="$item->divisi->color()">{{ $item->divisi->label() }}</flux:badge>
                            @else
                                {{ $item->divisi_lainnya }}
                            @endif
                        </flux:table.cell>
                        @if ($bolehUbah)
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEditModal({{ $item->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="hapus({{ $item->id }})" wire:confirm="Hapus item ini?" />
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">Belum ada item RAB pada bulan ini.</flux:table.cell>
                    </flux:table.row>
                @endforelse
                @if ($items->isNotEmpty())
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="font-semibold">Total</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold">Rp {{ number_format($grandTotal, 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell colspan="{{ $bolehUbah ? 2 : 1 }}"></flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showModal" class="max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <flux:heading size="lg">{{ $editId ? 'Ubah' : 'Tambah' }} Item RAB</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="uraian" label="Uraian" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="number" min="1" wire:model="qty" label="Qty" />
                    <flux:input type="number" min="0" wire:model="harga" label="Harga Satuan (Rp)" />
                </div>

                <flux:select wire:model.live="divisi" label="Divisi" placeholder="Pilih divisi...">
                    <flux:select.option value="">Pilih divisi...</flux:select.option>
                    @foreach ($divisiList as $d)
                        <flux:select.option value="{{ $d->value }}">{{ $d->label() }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="{{ \App\Livewire\Rab\Index::DIVISI_LAINNYA }}">Lainnya (nama orang)...</flux:select.option>
                </flux:select>

                @if ($divisi === \App\Livewire\Rab\Index::DIVISI_LAINNYA)
                    <flux:input wire:model="divisiLainnya" label="Divisi / Nama" placeholder="mis. Budi, Andi" />
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
