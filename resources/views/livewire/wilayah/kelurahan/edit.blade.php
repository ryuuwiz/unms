<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Edit Kelurahan</flux:heading>
        <flux:subheading>Perbarui informasi data kelurahan dan kecamatan terkait.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Kota / Kabupaten</flux:label>
                <flux:select wire:model.live="kota_id" placeholder="Pilih Kota / Kabupaten">
                    <flux:select.option value="">Pilih Kota / Kabupaten</flux:select.option>
                    @foreach ($kotas as $kota)
                        <flux:select.option value="{{ $kota->id }}">{{ $kota->nama_kota }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="kota_id" />
            </flux:field>

            <flux:field>
                <flux:label>Kecamatan Induk</flux:label>
                <flux:select wire:model="kecamatan_id" placeholder="Pilih Kecamatan" :disabled="!$kota_id">
                    <flux:select.option value="">Pilih Kecamatan</flux:select.option>
                    @foreach ($kecamatans as $kecamatan)
                        <flux:select.option value="{{ $kecamatan->id }}">{{ $kecamatan->nama_kecamatan }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="kecamatan_id" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Nama Kelurahan / Desa</flux:label>
            <flux:input wire:model="nama_kelurahan" placeholder="Contoh: Margasari" />
            <flux:error name="nama_kelurahan" />
        </flux:field>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="3" placeholder="Catatan area kelurahan..." />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('wilayah.kelurahan.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Perubahan</flux:button>
        </div>
    </form>
</div>
