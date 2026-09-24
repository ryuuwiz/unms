<flux:field>
    <flux:label>{{ $labelPilih }}</flux:label>
    <flux:input wire:model="scanKode" wire:keydown.enter.prevent="tambahUnit" placeholder="Pindai barcode atau ketik kode unit, lalu Enter" icon="qr-code" autocomplete="off" />
    @if ($unitKandidat->isNotEmpty())
        <flux:select x-on:change="if ($event.target.value) { $wire.pilihUnit(Number($event.target.value)) } $event.target.value = ''" class="mt-2" placeholder="...atau pilih dari daftar">
            <flux:select.option value="">...atau pilih dari daftar</flux:select.option>
            @foreach($unitKandidat as $kandidat)
                <flux:select.option value="{{ $kandidat->id }}">{{ $kandidat->kode }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif
    <flux:error name="unitIds" />

    @if ($unitTerpilih->isNotEmpty())
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach($unitTerpilih as $unit)
                <flux:badge size="sm" wire:key="unit-{{ $unit->id }}">
                    <span class="font-mono">{{ $unit->kode }}</span>
                    <flux:badge.close wire:click="hapusUnit({{ $unit->id }})" />
                </flux:badge>
            @endforeach
        </div>
        <flux:description>{{ $unitTerpilih->count() }} unit dipilih.</flux:description>
    @endif
</flux:field>
