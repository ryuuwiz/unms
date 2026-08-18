<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Edit Kota</flux:heading>
        <flux:subheading>Perbarui informasi data kota atau kabupaten.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nama Kota / Kabupaten</flux:label>
            <flux:input wire:model="nama_kota" placeholder="Contoh: Kota Bandung" autofocus />
            <flux:error name="nama_kota" />
        </flux:field>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="3" placeholder="Catatan atau keterangan area..." />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('wilayah.kota.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Perubahan</flux:button>
        </div>
    </form>
</div>
