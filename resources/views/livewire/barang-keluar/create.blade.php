<div class="mx-auto max-w-xl space-y-6">
    <div>
        <flux:heading size="xl">Catat Barang Keluar</flux:heading>
        <flux:subheading>Stok barang akan berkurang otomatis setelah dicatat. Sistem akan menolak jika stok tidak mencukupi.</flux:subheading>
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
                <flux:label>Jumlah Keluar</flux:label>
                <flux:input wire:model="jumlah_keluar" type="number" min="1" placeholder="0" />
                <flux:error name="jumlah_keluar" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Teknisi <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:input wire:model="teknisi" placeholder="Nama teknisi yang membawa barang" />
            <flux:error name="teknisi" />
        </flux:field>

        <flux:field>
            <flux:label>Keterangan <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="keterangan" rows="2" placeholder="Contoh: Pemasangan pelanggan baru SITE-XXXX" />
            <flux:error name="keterangan" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('barang-keluar.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
        </div>
    </form>
</div>
