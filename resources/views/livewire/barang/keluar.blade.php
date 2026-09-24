<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Barang Keluar</flux:heading>
            <flux:subheading>Pemakaian oleh teknisi dan penghapusbukuan barang rusak.</flux:subheading>
        </div>
        @can('barang.keluar')
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Catat Barang Keluar</flux:button>
        @endcan
    </div>

    <flux:card class="space-y-4 p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:input type="month" wire:model.live="bulan" label="Bulan" />
            <flux:input wire:model.live.debounce.300ms="search" label="Nama / Kode Barang" placeholder="Cari barang..." icon="magnifying-glass" />
            <flux:select wire:model.live="filterTeknisiId" label="Teknisi">
                <flux:select.option value="">Semua Teknisi</flux:select.option>
                @foreach($teknisis as $teknisi)
                    <flux:select.option value="{{ $teknisi->id }}">{{ $teknisi->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$mutasi">
            <flux:table.columns>
                <flux:table.column>No</flux:table.column>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Kode Barang</flux:table.column>
                <flux:table.column>Nama Barang</flux:table.column>
                <flux:table.column align="end">Jumlah Keluar</flux:table.column>
                <flux:table.column>Keterangan</flux:table.column>
                <flux:table.column>Teknisi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($mutasi as $baris)
                    <flux:table.row :key="$baris->id">
                        <flux:table.cell>{{ $mutasi->firstItem() + $loop->index }}</flux:table.cell>
                        <flux:table.cell>{{ $baris->tanggal->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono font-semibold">{{ $baris->jenisBarang->kode }}</span>
                            @if ($baris->units->isNotEmpty())
                                <div class="text-xs font-mono text-zinc-500">{{ $baris->units->pluck('kode')->implode(', ') }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $baris->jenisBarang->nama }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($baris->jumlah, 0, ',', '.') }} {{ $baris->jenisBarang->satuan }}</flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $baris->keterangan ?? '-' }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $baris->tipe->label() }}
                                @if ($baris->ticket)
                                    · <a href="{{ route('ticket.show', $baris->ticket) }}" class="text-blue-600 dark:text-blue-400 hover:underline" wire:navigate>{{ $baris->ticket->nomor_ticket }}</a>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $baris->teknisi->name ?? '-' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">Belum ada barang keluar pada bulan ini.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showModal" class="max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <flux:heading size="lg">Catat Barang Keluar</flux:heading>

            <div class="space-y-4">
                <flux:select wire:model.live="jenisId" label="Barang" placeholder="Pilih barang...">
                    @foreach($jenisList as $jenis)
                        <flux:select.option value="{{ $jenis->id }}">{{ $jenis->kode }} — {{ $jenis->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="jenisId" />
                @if ($jenisTerpilih)
                    <flux:description>Stok tersedia: {{ number_format((int) $stokTersedia, 0, ',', '.') }} {{ $jenisTerpilih->satuan }}</flux:description>
                @endif

                <flux:select wire:model.live="tipe" label="Tipe">
                    @foreach($tipes as $t)
                        <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model="tanggal" label="Tanggal" />

                @if ($jenisTerpilih?->dilacak_per_unit)
                    @include('livewire.barang.partials.pilih-unit', ['labelPilih' => 'Unit yang keluar'])
                @else
                    <flux:input type="number" min="1" wire:model="jumlah" :label="'Jumlah'.($jenisTerpilih ? ' ('.$jenisTerpilih->satuan.')' : '')" />
                @endif

                <flux:select wire:model="teknisiId" label="Teknisi Penerima" placeholder="Pilih teknisi...">
                    @foreach($teknisis as $teknisi)
                        <flux:select.option value="{{ $teknisi->id }}">{{ $teknisi->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="teknisiId" />

                <flux:input wire:model="nomorTicket" label="Nomor Tiket (opsional)" placeholder="TCK-2026-000123" />
                <flux:textarea wire:model="keterangan" label="Keterangan" rows="2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
