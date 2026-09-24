<div class="mx-auto max-w-xl space-y-6">
    <div>
        <flux:heading size="xl">Catat Barang Masuk</flux:heading>
        <flux:subheading>Stok barang akan bertambah otomatis setelah dicatat.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <x-searchable-select field="barang_id" label="Barang" placeholder="Cari kode atau nama barang..." :required="true" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Tanggal</flux:label>
                <flux:input wire:model="tanggal" type="date" />
                <flux:error name="tanggal" />
            </flux:field>

            <flux:field>
                <flux:label>Jumlah Masuk</flux:label>
                <flux:input wire:model="jumlah_masuk" type="number" min="1" placeholder="0" />
                <flux:error name="jumlah_masuk" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="2" placeholder="Contoh: Pembelian dari supplier X" />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('barang-masuk.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
        </div>
    </form>
</div>
