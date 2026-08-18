<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Edit Kecamatan</flux:heading>
        <flux:subheading>Perbarui informasi data kecamatan.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Kota / Kabupaten Induk</flux:label>
            <flux:select wire:model="kota_id" placeholder="Pilih Kota / Kabupaten">
                <flux:select.option value="">Pilih Kota / Kabupaten</flux:select.option>
                @foreach ($kotas as $kota)
                    <flux:select.option value="{{ $kota->id }}">{{ $kota->nama_kota }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="kota_id" />
        </flux:field>

        <flux:field>
            <flux:label>Nama Kecamatan</flux:label>
            <flux:input wire:model="nama_kecamatan" placeholder="Contoh: Buahbatu" />
            <flux:error name="nama_kecamatan" />
        </flux:field>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="3" placeholder="Catatan area kecamatan..." />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('wilayah.kecamatan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Perubahan</flux:button>
        </div>
    </form>
</div>
