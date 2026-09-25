<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Unit Barang</flux:heading>
            <flux:subheading>Setiap unit barang yang dilacak (modem/ONT) beserta kode, status, dan label barcode.</flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="pilihSemua" icon="check-circle">Pilih Semua Hasil Filter</flux:button>
            @if ($dipilih !== [])
                <flux:button wire:click="batalPilih" variant="ghost">Batal Pilih</flux:button>
            @endif
            {{-- POST: ratusan ID unit tidak muat di query string. --}}
            <form method="POST" action="{{ route('barang.label') }}" target="_blank">
                @csrf
                @foreach ($dipilih as $id)
                    <input type="hidden" name="unit[]" value="{{ $id }}">
                @endforeach
                <flux:button type="submit" icon="printer" :disabled="$dipilih === []">Cetak Label ({{ count($dipilih) }})</flux:button>
            </form>
        </div>
    </div>

    <flux:card class="space-y-4 p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:select wire:model.live="jenisId" label="Barang">
                <flux:select.option value="">Semua Barang</flux:select.option>
                @foreach($jenisList as $jenis)
                    <flux:select.option value="{{ $jenis->id }}">{{ $jenis->kode }} — {{ $jenis->nama }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="status" label="Status">
                <flux:select.option value="">Semua Status</flux:select.option>
                @foreach($statuses as $st)
                    <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="search" label="Kode / Serial" placeholder="MDM-NEW-BF-..." icon="magnifying-glass" />
        </div>

        <flux:table :paginate="$units">
            <flux:table.columns>
                <flux:table.column class="w-8"></flux:table.column>
                <flux:table.column>Kode Unit</flux:table.column>
                <flux:table.column>Barang</flux:table.column>
                <flux:table.column>Kondisi</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($units as $unit)
                    <flux:table.row :key="$unit->id">
                        <flux:table.cell><flux:checkbox wire:model.live="dipilih" value="{{ $unit->id }}" /></flux:table.cell>
                        <flux:table.cell><span class="font-mono font-semibold">{{ $unit->kode }}</span>@if($unit->serial_number)<div class="text-xs text-zinc-500">SN {{ $unit->serial_number }}</div>@endif</flux:table.cell>
                        <flux:table.cell>{{ $unit->jenisBarang->nama }}</flux:table.cell>
                        <flux:table.cell>{{ $unit->kondisi->kode }} — {{ $unit->kondisi->nama }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$unit->status->color()">{{ $unit->status->label() }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">Belum ada unit yang sesuai filter.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
