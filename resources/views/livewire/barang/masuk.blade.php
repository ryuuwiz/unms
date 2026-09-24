<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Barang Masuk</flux:heading>
            <flux:subheading>Pembelian, saldo awal, dan pengembalian unit dari pelanggan.</flux:subheading>
        </div>
        @can('barang.masuk')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Catat Barang Masuk</flux:button>
        @endcan
    </div>

    @if ($mutasiBaruId)
        <flux:callout variant="success" icon="qr-code">
            <flux:callout.heading>Unit baru berhasil dibuat</flux:callout.heading>
            <flux:callout.text>Cetak label barcode untuk unit yang baru masuk.</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('barang.label', ['mutasi' => $mutasiBaruId])" target="_blank" icon="printer">Cetak Label</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <flux:card class="space-y-4 p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:input type="month" wire:model.live="bulan" label="Bulan" />
            <flux:input wire:model.live.debounce.300ms="search" label="Nama / Kode Barang" placeholder="Cari barang..." icon="magnifying-glass" />
        </div>

        <flux:table :paginate="$mutasi">
            <flux:table.columns>
                <flux:table.column>No</flux:table.column>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Kode Barang</flux:table.column>
                <flux:table.column>Nama Barang</flux:table.column>
                <flux:table.column align="end">Jumlah Masuk</flux:table.column>
                <flux:table.column>Tipe</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($mutasi as $baris)
                    <flux:table.row :key="$baris->id">
                        <flux:table.cell>{{ $mutasi->firstItem() + $loop->index }}</flux:table.cell>
                        <flux:table.cell>{{ $baris->tanggal->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono font-semibold">{{ $baris->jenisBarang->kode }}</span>
                            @if ($baris->units->isNotEmpty())
                                <div class="text-xs font-mono text-zinc-500">{{ $baris->units->pluck('kode')->take(3)->implode(', ') }}@if($baris->units->count() > 3) +{{ $baris->units->count() - 3 }} @endif</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $baris->jenisBarang->nama }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($baris->jumlah, 0, ',', '.') }} {{ $baris->jenisBarang->satuan }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $baris->tipe->label() }}
                            @if ($baris->keterangan)<div class="text-xs text-zinc-500">{{ $baris->keterangan }}</div>@endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">Belum ada barang masuk pada bulan ini.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showModal" class="max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <flux:heading size="lg">Catat Barang Masuk</flux:heading>

            <div class="space-y-4">
                <flux:select wire:model.live="jenisId" label="Barang" placeholder="Pilih barang...">
                    @foreach($jenisList as $jenis)
                        <flux:select.option value="{{ $jenis->id }}">{{ $jenis->kode }} — {{ $jenis->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="jenisId" />

                <flux:select wire:model.live="tipe" label="Tipe">
                    @foreach($tipes as $t)
                        <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model="tanggal" label="Tanggal" />

                @if ($jenisTerpilih?->dilacak_per_unit && $tipe === \App\Enums\Barang\TipeMutasiBarang::Pengembalian->value)
                    @include('livewire.barang.partials.pilih-unit', ['labelPilih' => 'Unit yang dikembalikan (berstatus Terpasang)'])
                @else
                    <flux:input type="number" min="1" wire:model="jumlah" :label="'Jumlah'.($jenisTerpilih ? ' ('.$jenisTerpilih->satuan.')' : '')" />
                @endif

                @if ($jenisTerpilih?->dilacak_per_unit)
                    <flux:select wire:model="kondisiId" label="Kondisi" placeholder="Pilih kondisi...">
                        @foreach($kondisis as $kondisi)
                            <flux:select.option value="{{ $kondisi->id }}">{{ $kondisi->kode }} — {{ $kondisi->nama }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="kondisi" />

                    @if ($tipe !== \App\Enums\Barang\TipeMutasiBarang::Pengembalian->value)
                        <flux:select wire:model="brandId" label="Brand (opsional)" placeholder="Tanpa brand">
                            <flux:select.option value="">Tanpa brand</flux:select.option>
                            @foreach($brands as $brand)
                                <flux:select.option value="{{ $brand->id }}">{{ $brand->kode }} — {{ $brand->nama }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>Kode unit dibuat otomatis: KATEGORI-KONDISI-BRAND-NOMOR, mis. MDM-NEW-BF-240.</flux:description>
                    @endif
                @endif

                <flux:textarea wire:model="keterangan" label="Keterangan" rows="2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
